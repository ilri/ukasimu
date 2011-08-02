<?php

/**
 * This is the main class that will do all the hard work of aliquoting. It keeps track of the current settings, determines automagically where
 * each aliquot will be saved based on previous aliquots.
 * 
 * Version 1.0
 *
 * ------------------------------------------------------------------------------------------------------------
 * 
 * This is a major overhaul of the aliquoting system
 * <b>Changes to this version</b><br />
 * Method of coding changed from procedural to OO
 * Added default settings when defining the aliquot settings
 *
 * 
 * @category   Aliquoting
 * @package    Aliquots
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v1.0
 */
class Aliquots extends DBase{

   /**
    * @var Object An object with the database functions and properties. Implemented here to avoid having a million & 1 database connections
    */
   public $Dbase;

   /**
    * @var HTML_String  The links that will appear at the bottom of the page
    */
   public $footerLinks;

   /**
    * @var  array    An array that will hold all the settings as set by the user and as determined by the system
    * 
    * At the height of program execution, this array may look like:
    * 
    * <code>
    * Array
	 *	(
	 *	    [parent_format] => BDT 6
	 *	    [aliquot_format] => AVAQ 5
	 *	    [noOfAliquots] => 3
	 *	    [trays] => Array
	 *	        (
	 *	            [0] => Array
	 *	                (
	 *	                    [name] => TAVQC 5
	 *	                    [descr] => ICIPE
	 *	                    [size] => 81
	 *	                )
	 *	
	 *	            [1] => Array
	 *	                (
	 *	                    [name] => TAVQA 5
	 *	                    [descr] => DVS
	 *	                    [size] => 81
	 *	                )
	 *	
	 *	            [2] => Array
	 *	                (
	 *	                    [name] => TAVQL 5
	 *	                    [descr] => ILRI
	 *	                    [size] => 100
	 *	                )
	 *	
	 *	        )
	 *	
	 *	    [searchItem] => AVAQ09091
	 *	    [parent_sample] => BDT068460
	 *	    [animal] => SON, SON00104, S008
	 *	    [save_tray] => TAVQA00001
	 *	    [save_position] => 1
	 *	    [prev_sample] => AVAQ07476
	 *	    [aliquotIndex] => 1
	 *	)
    * </code>
    */
   private $settings;
   
   public function  __construct() {
      //this looks so ugly...bt so far thats what I gat
      $this->Dbase = new DBase();
      if($this->Dbase->dbcon->connect_error || (isset($this->Dbase->dbcon->errno) && $this->Dbase->dbcon->errno!=0)) {
         die('Something wicked happened when connecting to the dbase.');
      }
      if(isset($this->Dbase)) {
         $this->Dbase->InitializeConnection();
         $this->Dbase->InitializeLogs();
      }
   }
   
   /**
    * Controls the program execution
    */
   public function TrafficController(){
      if(OPTIONS_REQUESTED_MODULE == '') $this->HomePage();
      elseif(OPTIONS_REQUESTED_MODULE == 'sort_aliquots'){
         $this->SampleProcessing();
         $this->footerLinks .= " | <a href=''>Sort Home</a>";
      }
   }
   
   /**
    * Creates the home page for the system.
    * 
    * @param   string   $addinfo    (Optional) Any additional information that needs to be displayed on the home page
    */
   private function HomePage($addinfo = ''){
?>
<div>
   <div id='addinfo'><?php echo "$addinfo"; ?></div>
   <ol class='ol_li'>
      <li><a href="?page=sort_aliquots">Sort Aliquots</a></li>
      <li><a href="?page=merge">Merge different collections</a></li>
      <li><a href="?page=backup">Backup</a></li>
   </ol>
</div>
<?php
   }

   /**
    * The main aliquoting function. Calls the appropriate methods to do the necessary functions
    * 
    * @return  Nothing
    */
   private function SampleProcessing(){
      echo '<form name="form" action="?page=sort_aliquots" method="POST" id="searchFormId">';      //start the form
      //check the aliquoting settings
      if(isset($_POST['aliquot_settings']) && is_array($_POST['aliquot_settings'])){
         if($this->AliquotingSettings() == 1){
            echo "</form>";   //close the form and return since we have an error
            return;
         }
         $this->GenerateTopPanel();
         $this->GenerateLowerPanel();
      }
      else{
         $this->InitiateAliquotingProcess();     //we dont have the aliquot settings...meaning that we need to start the aliquoting process
         return;
      }
      return;
   }

   /**
    * Merges the different aliquot settings that might be defined from the client side
    * 
    * @return  integer  0
    * @todo    Add validation of the data at this point to avoid data validation being scattered all over the code
    */
   private function AliquotingSettings(){
      $this->settings = array_merge(
         $_POST['aliquot_settings'],
         array(
            'searchItem' => strtoupper($_POST['searchItem']),
            'parent_sample' => $_POST['parent'],
            'animal' => $_POST['curAnimal'],
            'save_tray' => $_POST['nextTray'],
            'save_position' => $_POST['nextPosition'],
            'prev_sample' => $_POST['prevSample'],
            'aliquotIndex' => $_POST['aliquotIndex']
         )
      );
      $this->Dbase->CreateLogEntry($this->settings['searchItem'], 'aliquot');    //just log the way we are receiving the searched items
      return 0;
   }
   
   /**
    * Saves an aliquot to the database. Performs the integrity checks first before saving the aliquot
    * 
    * @return  mixed    Returns 0 if all goes ok, else it returns a string with a message of the error that has occurred.
    */
   private function SaveAliquot(){
      $save_position = $this->settings['save_position'];
      $save_tray = $this->settings['save_tray'];
      $prev_sample = $this->settings['prev_sample'];
      $aliquotIndex = $this->settings['aliquotIndex'];
      $aliqNo = $aliquotIndex + 1;
      $tray_size = $this->settings['trays'][$aliquotIndex]['size'];
      
  /*    
      $this->Dbase->CreateLogEntry(
         "Aliquot Format => {$this->settings['aliquot_format2use']}
        Previous Sample => $prev_sample
        Tray Format => {$this->settings['trays_format2use'][$aliquotIndex]}
        Save in Tray => $save_tray
        Save Position => $save_position
        Aliquot Index => $aliquotIndex", 'debug'
      );
*/
      if(preg_match('/^' . $this->settings['aliquot_format2use'] . '$/i', $prev_sample) && isset($save_tray) && $save_tray != '' && is_numeric($save_position) 
              && is_numeric($aliquotIndex)){
         //we have something to save, so save it, bt first confirm that the aliquot is right
         //check if there are other aliquots from this sample
         $this->Dbase->query = "select * from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='{$this->settings['parent_sample']}' order by a.aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         elseif(count($aliq) + 1 > $this->settings['noOfAliquots']){
            //if there are enough aliquots it means we dont need to add another aliquot
            return "Error! A sample can only have {$this->settings['noOfAliquots']} aliquots.";
         }
//      echo '<pre>'.print_r($this->settings, true).'</pre>';
         if(preg_match('/^' . $this->settings['trays_format2use'][$aliquotIndex] . '$/i', $save_tray) && ($save_position > 0 && $save_position <= $tray_size)){
            $parentSampleId = $this->Dbase->GetSingleRowValue('aliq_samples', 'id', 'label', $this->settings['parent_sample']);
            if($parentSampleId == -2){
               return "There was an error while fetching data from the database.";
            }
            else{
               $this->settings['currentAliquots'] = $aliq;
               $cols = array('label', 'parent_sample', 'aliquot_number', 'tray', 'position');
               $colvals = array(strtoupper($prev_sample), $parentSampleId, $aliquotIndex+1, $save_tray, $save_position);
               $results = $this->Dbase->InsertData("aliquots", $cols, $colvals);
               if($results == 1) return "There was an error while saving the aliquot $prev_sample.";
               return 0;
               //echo "save this $save_aliquot $save_position $save_tray";
            }
         }
         else return "Unable to save $prev_sample($aliqNo) in tray $save_tray at position $save_position.";
      }
      else return 0;
   }
   
   /**
    * Initiates the saving of the sample which was previously scanned and then determines the saving parameters of the current aliquot
    * 
    * @return  mixed    Returns a string with the error message in case there was an error, else it returns an array with the current aliquot settings
    */
   private function CurrentSampleSettings(){
      //check whether we have all the parent sample metadata
      if( isset($this->settings['parent_sample']) && !in_array($this->settings['parent_sample'], array('', 'undefined')) ){
         //check whether there is some saving that should take place before looking for other things
         $res = $this->SaveAliquot();
         if(is_string($res)){
            $this->settings['save_position'] = '';
            $this->settings['save_tray'] = '';
            return $res;
         }
         //get the metadata for this sample, ie from which animal its coming from and the animal metadata
         $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location from aliq_samples as a inner join aliq_animals as b on a.animal_id=b.id "
                 . "where lower(a.label) like lower('{$this->settings['parent_sample']}')";
//         $this->Dbase->CreateLogEntry('', 'debug', true);
         $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($results == 1) return "There was an error while fetching data from the database.";
         elseif(count($results) > 1){ //we have more than one sample in the dbase. THIS IS A HUGE ERROR AS WE ARE ONLY DEALING WITH UNIQUE SAMPLES
            return "There is an error in the database. There can be only one sample with this id.";
         }
         elseif(count($results) == 0) return 'The sample is not in the database';   //the sample is not in the dbase
         elseif(count($results) == 1){   //we have a hit, the sample is in the dbase
            $animal = $results[0]['animal_id'];
            $curAliquotIndex = null;
            //check if there are other aliquots from this sample
            $this->Dbase->query = "select a.* from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='{$this->settings['parent_sample']}' order by a.aliquot_number";
            $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
            if($aliq == 1) return "There was an error while fetching data from the database.";
            //determine which aliquot number we really want. will take care if we delete an aliquot of a institution defined first
            //expects to find a matching set of noOfAliquots and the index. In case this match is inconsistent,
            //create an generate a position to correct this
            $generated = false;
            for($i = 0; $i < $this->settings['noOfAliquots']; $i++){
               if($i + 1 != $aliq[$i]['aliquot_number']){
                  $generated = true;
                  $res = $this->NewNextSlot($i);
                  if(is_string($res)) return $res;
                  $curAliquotIndex = $i;
                  break;
               }
            }
            if($generated == false) $this->NewNextSlot(count($aliq) + 1);
            //now create the place holder for this new sample
            $tray = "<input type='text' name='nextTray' size='15px' id='nextTrayId' value='{$this->settings['nextBox']}' />";
            $position = "<input type='text' name='nextPosition' size='15px' id='nextPositionId' value='{$this->settings['nextPosition']}' />"
                    . "<input type='hidden' size='15px' name='saveAliquot' id='saveAliquotId' value='{$this->settings['searchItem']}' />"
                    . "<input type='hidden' size='15px' name='aliquotIndex' id='aliquotNumberId' value='$curAliquotIndex' />";
            $aliq[count($aliq)] = array('label' => $this->settings['searchItem'], 'tray' => $tray, 'position' => $position);
            return $aliq;
         }
      }
   }

   /**
    * Calculates the next empty slot in the next box. This is the position where the current aliquot will be placed.
    * 
    * Since we expect to have multiple aliquots for each sample, we assign each aliquot an index which makes it easier for us to be able to 
    * know to which tray it is going to be saved and which set validation criteria will be used
    *
    * @param   integer  $aliquotIndex  The index of the aliquot which we want to place
    * @return  mixed    Returns a string with an error message in case of an error, else it returns 0
    */
   private function NewNextSlot($aliquotIndex){
      $trayType = strtolower($this->settings['trays_format2use'][$aliquotIndex]);  //type must be from 1-4
      $pat = "and lower(b.label) rlike '^" . strtolower($this->settings['parent_format2use']) . "$'";
      $this->Dbase->query = "select a.tray, a.position from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id "
              . "where a.tray is not null and a.position is not null and lower(a.tray) rlike '^$trayType$' $pat order by a.tray desc, a.position desc limit 0,1";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
//      $this->Dbase->CreateLogEntry('', 'debug', true);
      if($results == 1) return "There was an error while determining the next slot.";
      
      preg_match('/^[a-z]+/i', $trayType, $res);
      $trayPrefix = strtoupper($res[0]);
      preg_match('/\{[0-9]+\}/i', $trayType, $res);
      preg_match('/[0-9]+/', $res[0], $res);
      $intCount = $res[0]; //echo $intCount;
      if($results[0]['tray'] == NULL || $results[0]['position'] == NULL){
         $this->settings['nextBox'] = $trayPrefix . str_repeat('0', $intCount - 1) . '1';
         $this->settings['nextPosition'] = 1;
      }
      else{
         if($results[0]['position'] == $this->settings['trays'][$aliquotIndex]['size']){
            $nextInt = (substr($results[0]['tray'], -$intCount) + 1);
            $this->settings['nextBox'] = $trayPrefix . str_repeat('0', $intCount - strlen($nextInt)) . $nextInt;
            $this->settings['nextPosition'] = 1;
         }
         else{
            $this->settings['nextBox'] = $results[0]['tray'];
            $this->settings['nextPosition'] = $results[0]['position'] + 1;
         }
      }
      return 0;
   }
   
   /**
    * Creates the interface that will be used to initialize the aliquoting process
    */
   private function InitiateAliquotingProcess(){
?>
   <div id="aliquot_settings">
      <table>
         <tr><td>Parent Sample Format</td><td><input type='text' id="parent_format" name='aliquot_settings[parent_format]' value='BDT 6' size="15" /></td>
            <td>Aliquot Format</td><td><input type='text' id="aliquot_format" name='aliquot_settings[aliquot_format]' value="AVAQ 5" size="15" /></td>
            <td>No of Aliquots</td><td><input type='text' name='aliquot_settings[noOfAliquots]' size="3" value="3" id='aliquot_number_id' /></td></tr>
         <tr id='aliquotNos'>&nbsp;</tr>
      </table>
      <div id='links'>
         <input type='button' name='find' value='Save' /> <input type='button' name='cancel' value='Cancel' />
      </div>
   </div>
   <script type="text/javascript">
      $('[name=find]').bind('click', Samples.generateAliquots);
   </script>
<?php
   }

   /**
    * Generates the top panel with the settings currently being used
    *
    * @param   array    $aliq    An array with the data which the user has already defined
    * @return  string   Returns the HTML code that will be used to create this top panel
    */
   private function GenerateTopPanel(){
      $this->settings['aliquot_format'] = strtoupper($this->settings['aliquot_format']);
      $parts = explode(' ', $this->settings['aliquot_format']);
      $aliquot_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
      $this->settings['parent_format'] = strtoupper($this->settings['parent_format']);
      $parts = explode(' ', $this->settings['parent_format']);
      $parent_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
      $no_of_aliquots = $this->settings['noOfAliquots'];
      $this->settings['parent_format2use'] = $parent_format;
      $this->settings['aliquot_format2use'] = $aliquot_format;
      $this->settings['trays_format2use'] = array();
      $this->settings['just_trays'] = array();

?>
      <div id='aliquot_settings'>
         <table>
            <tr><td>Parent Format:<b><?php echo "{$this->settings['parent_format']}"; ?></b><input type='hidden' name='aliquot_settings[parent_format]' value='<?php echo "{$this->settings['parent_format']}"; ?>' /></td>
               <td>&nbsp;&nbsp;Aliquot Format:<b><?php echo "{$this->settings['aliquot_format']}"; ?></b><input type='hidden' name='aliquot_settings[aliquot_format]' value='<?php echo "{$this->settings['aliquot_format']}"; ?>' /></td>
               <td>&nbsp;&nbsp;No of Aliquots:<b><?php echo "$no_of_aliquots"; ?></b><input type='hidden' name='aliquot_settings[noOfAliquots]' value='<?php echo "$no_of_aliquots"; ?>' /></td></tr>
            <tr id='aliquotNos'><td colspan="3"><table>
<?php

         for($i=0; $i<count($this->settings['trays']); $i++){
            $t=$this->settings['trays'][$i];
            $parts = explode(' ', $t['name']);
            $trayName =  trim($parts[0]).'[0-9]{'."$parts[1]}";
            $this->settings['trays_format2use'][] = $trayName;
            $this->settings['just_trays'][] = $t['name'];
            ?>
            <tr><td>Tray:<b><?php echo "{$t['name']}"; ?><input type='hidden' name='aliquot_settings[trays][<?php echo "$i"; ?>][name]' value='<?php echo "{$t['name']}"; ?>' /></b></td>
            <td>Description:<b><?php echo "{$t['descr']}"; ?><input type='hidden' name='aliquot_settings[trays][<?php echo "$i"; ?>][descr]' value='<?php echo "{$t['descr']}"; ?>' /></b></td>
            <td>Size:<b><?php echo "{$t['size']}"; ?><input type='hidden' name='aliquot_settings[trays][<?php echo "$i"; ?>][size]' value='<?php echo "{$t['size']}"; ?>' /></b></td></tr>
           <?php
         }
?>
         </table></td></tr>
         </table>
      </div>
<?php
      }

   /**
    * Generates the lower panel that shows the searched sample details as well as where an aliquot should be or is stored
    */
   private function GenerateLowerPanel(){
      $errorClass = 'error';
      $parentSampleId = '';
      $addinfo = '';
      $aliquots = array();
      
      if(isset($this->settings['searchItem']) && !in_array($this->settings['searchItem'], array('', 'undefined'))){
         //check if we have a parent aliquot
         //as long as we have a search item, check if we need to save the sample
         $res = $this->CurrentSampleSettings();
         if(is_string($res)) $addinfo = $res;
         else{
            if(preg_match('/^' . $this->settings['parent_format2use'] . '$/i', $this->settings['searchItem'])){
               $res = $this->IsParentSample();
               if(is_string($res)) $addinfo = $res;
               else $aliquots = $res;
            }
            elseif(preg_match("/^".$this->settings['aliquot_format2use']."$/i", $this->settings['searchItem'])) $aliquots = $res;
            else $addinfo = 'Unrecognized Sample. Try again';
         }
         
         //ok now we r good
         $searchedSample = "Searched Sample: <b>{$this->settings['searchItem']}</b>";
      }
      else{
         $addinfo = 'Please enter or scan a sample.';
         $errorClass = 'no_error';
         $searchedSample = '&nbsp;';
      }
      if($addinfo == '' || !isset($addinfo)){
         $addinfo = 'Enter or scan the aliquot you want to sort.';
         $errorClass = 'no_error';
      }
      
echo <<<Content
   <div id="search_form">
      <span id="search_mssg">
      <div class='$errorClass'>
         $addinfo<br />$searchedSample
      </div>
      </span><br />
      <input type="text" name="searchItem" size="15px" id="searchItemId" value="" onkeyup="Samples.simulateEnterButton(event);" />
      <input type="button" name="find" id="submitId" value="FIND" onClick="Samples.search();" />
      <input type="hidden" value="{$this->settings['searchItem']}" name="prevSample" id="prevSampleId" />
      <input type="hidden" value="{$this->settings['parent_sample']}" name="parent" id="parentSampleId" />
      <input type="hidden" value="{$this->settings['animal']}" name="curAnimal" id="animalId" />
      <input type="hidden" value="{$this->settings['save_tray']}" name="nextTray" id="parentSampleId" />
      <input type="hidden" value="{$this->settings['save_position']}" name="nextPosition" id="animalId" />

      <div id='search_sort_results'>
         <div id='animal_data'>
            <table>
               <tr><td colspan='2'>Animal Id: <b>{$this->settings['animal']}</b></td><td colspan='2'>Source Sample: <b>{$this->settings['parent_sample']}</b></td></tr>
               <tr class='last_row'><td colspan='2'>Organism: <b>{$this->settings['organism']}</b></td><td colspan='2'>Comments: <b>{$this->settings['comment']}</b></td></tr>
            </table>
         </div>
         <div id='aliquots'>
            <table cellspacing='0'>
               <tr><th class='left'>Destination</th><th>Aliquot</th><th>TrayId</th><th>Position in Tray</th><th>Actions</th></tr>
Content;
            
      /**
       * This is the point where the trays as defined and the defined aliquots converge. This system relies on one setting, ie
       * the trays as defined corresponds to the aliquot number, as they will be entered/scanned. So at this stage, since we ordered the aliquots
       * based on the aliquot number, we can say that the trays and the aliquots converge here
       */
      $bClass = "";
      $addClass = '';
      /**
       * var array $assignedTrays Keeps a track of the trays which have been assigned aliquots from a parent sample, such that we have a correct
       * naming of trays in case an aliquot of a tray which has a higher index is deleted
       */
      $assignedTrays = array();
      $noOfAliquots = count($this->settings['trays']);
      for($i = 0; $i < $noOfAliquots; $i++){
         if(isset($aliquots[$i]['aliquot_number'])){
            /**
             * if the aliquot is already defined, display the institution its intended to go to by getting the tray name using the aliquot number
             * and tray indexes. Add this fetched tray to the list of assignedTrays
             */
            $predTray = $this->settings['trays'][$aliquots[$i]['aliquot_number'] - 1];
         }
         else{
            /**
             * naturally in case an aliquot is not defined, we would get the next institution to receive an aliquot. However if an aliquot of an
             * insitution with a lower index is deleted, the institution with higher indexes might already have received an aliquot. To avoid this
             * check which insitution is not on the list of assignedTrays and get the first one which is not defined.
             */
            $t = array_diff($this->settings['just_trays'], $assignedTrays);
            $predTray = $this->settings['trays'][key($t)];
         }
         $assignedTrays[] = $predTray['name'];
         if($i == count($this->settings['trays']) - 1){
            $bClass = "class='bottom'";
            $addClass = 'bottom';
         }
         if($aliquots[$i]['tray'] == '') $aliquots[$i]['tray'] = '&nbsp;';
         if($aliquots[$i]['position'] == '') $aliquots[$i]['position'] = '&nbsp;';
         $actions = ($aliquots[$i]['label'] == '') ? '&nbsp;' : "<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"edit\")'>Edit</a>&nbsp;<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"delete\")'>Delete</a>";
         if($aliquots[$i]['label'] == '') $aliquots[$i]['label'] = '&nbsp;';
         echo "<tr class=''><td class='left $addClass'>" . $predTray['descr'] . ":</td><td $bClass>{$aliquots[$i]['label']}" .
                 "</td><td $bClass>{$aliquots[$i]['tray']}</td><td $bClass>{$aliquots[$i]['position']}</td><td $bClass>$actions</td></tr>";
      }
?>
         </table>
      </div>
   </div>
</div>
</form>
<?php
   }
   
   /**
    * Check if the searched sample is a parent sample...we suspect it to be one.
    * 
    * @return  mixed    returns a string with the error message if there was an error else return an array with the parent sample metadata
    */
   private function IsParentSample(){
//      $this->Dbase->CreateLogEntry(print_r($this->settings, true), 'debug');
      //try and see that it is in the db before making it the parent sample
      $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location, a.comment, b.organism from aliq_samples as a inner join aliq_animals as b on a.animal_id=b.id "
              . "where lower(a.label) = lower('{$this->settings['searchItem']}')";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      //LogError();
      if($results == 1) return "There was an error while fetching data from the database.";
      elseif(count($results) == 0){
         $this->settings['parentSampleId'] = '';
         $this->settings['animal'] = '';
         return "The sample {$this->settings['searchItem']} was not found in the database.";
      }
      elseif(count($results) != 1){
         return "There is an error in the database. There can be only one sample with this id.";
      }
      else{
         //check if we have some aliquots from this sample
         $this->settings['comment'] = mysql_real_escape_string($results[0]['comment']);
         $this->settings['organism'] = $results[0]['organism'];
         $this->settings['parent_sample'] = strtoupper($this->settings['searchItem']);
         $this->settings['animal'] = $results[0]['animal_id'];

         //check if there are other aliquots from this sample
         $this->Dbase->query = "select a.* from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='{$this->settings['parent_sample']}' order by a.aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         return $aliq;
      }
   }
}
?>