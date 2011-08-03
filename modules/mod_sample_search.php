<?php
/**
* Copyright 2011 ILRI
* 
* This file is part of ukasimu.
* 
* ukasimu is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
* 
* ukasimu is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with ukasimu.  If not, see <http://www.gnu.org/licenses/>.
*/


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
 * Settings and animal data stored as a json object instead of different input fields
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
	 * (
	 *     [parent] => Array
	 *         (
	 *             [label] => BDT068485
	 *             [comment] => 
	 *             [organism] => cattle
	 *             [animal] => SON, SON00105, S011
	 *         )
	 * 
	 *     [aliquot2save] => Array
	 *         (
	 *         )
	 * 
	 *     [settings] => Array
	 *         (
	 *             [parent_format] => BDT 6
	 *             [aliquot_format] => AVAQ 5
	 *             [noOfAliquots] => 3
	 *             [trays] => Array
	 *                 (
	 *                     [0] => Array
	 *                         (
	 *                             [name] => TAVQC 5
	 *                             [descr] => ICIPE
	 *                             [size] => 81
	 *                             [format2use] => TAVQC[0-9]{5}
	 *                             [just_trays] => TAVQC 5
	 *                         )
	 * 
	 *                     [1] => Array
	 *                         (
	 *                             [name] => TAVQA 5
	 *                             [descr] => DVS
	 *                             [size] => 81
	 *                             [format2use] => TAVQA[0-9]{5}
	 *                             [just_trays] => TAVQA 5
	 *                         )
	 * 
	 *                     [2] => Array
	 *                         (
	 *                             [name] => TAVQL 5
	 *                             [descr] => ILRI
	 *                             [size] => 100
	 *                             [format2use] => TAVQL[0-9]{5}
	 *                             [just_trays] => TAVQL 5
	 *                         )
	 * 
	 *                 )
	 * 
	 *             [aliquot_format2use] => AVAQ[0-9]{5}
	 *             [parent_format2use] => BDT[0-9]{6}
	 *         )
	 * 
	 *     [searchItem] => AVAQ07477
	 *     [presaved_sample] => 1
	 *     [currentAliquots] => Array
	 *         (
	 *             [0] => Array
	 *                 (
	 *                     [id] => 59
	 *                     [label] => AVAQ07477
	 *                     [parent_sample] => 14
	 *                     [aliquot_number] => 1
	 *                     [tray] => TAVQC00001
	 *                     [position] => 2
	 *                     [parent] => BDT068485
	 *                 )
	 * 
	 *             [1] => Array
	 *                 (
	 *                     [id] => 60
	 *                     [label] => AVAQ35442
	 *                     [parent_sample] => 14
	 *                     [aliquot_number] => 2
	 *                     [tray] => TAVQA00001
	 *                     [position] => 2
	 *                     [parent] => BDT068485
	 *                 )
	 * 
	 *             [2] => Array
	 *                 (
	 *                     [id] => 61
	 *                     [label] => AVAQ07468
	 *                     [parent_sample] => 14
	 *                     [aliquot_number] => 3
	 *                     [tray] => TAVQL00001
	 *                     [position] => 2
	 *                     [parent] => BDT068485
	 *                 )
	 * 
	 *         )
	 * 
	 * )
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
      if(OPTIONS_REQUESTED_MODULE == ''){
         $this->footerLinks = '';
         $this->HomePage();
      }
      elseif(OPTIONS_REQUESTED_MODULE == 'sort_aliquots'){
         $this->SampleProcessing();
         $this->footerLinks .= " | <a href=''>Sort Home</a>";
      }
      elseif(OPTIONS_REQUESTED_MODULE == 'backup') $this->CreateDbDump();
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
      <li><a href="?page=sort_aliquots">Aliquot Samples</a></li>
      <li><a href="?page=merge">Merge different collections</a></li>
      <li><a href="mod_ajax_calls.php?page=backup">Backup</a></li>
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
      //check the aliquoting settings
//      $this->Dbase->CreateLogEntry("POST:\n".print_r($_POST, true), 'debug');
      if( (isset($_POST['aliquot_settings']) && is_array($_POST['aliquot_settings'])) || isset($_POST['settings']) ){
         $this->AliquotingSettings();
         $this->GenerateTopPanel();
         $this->GenerateLowerPanel();
      }
      else{
         echo '<form name="form" method="POST" id="searchFormId">';      //start the form
         $this->InitiateAliquotingProcess();     //we dont have the aliquot settings...meaning that we need to start the aliquoting process
         echo "</form>";   //close the form
      }
      return;
   }
   
   /**
    * Creates the interface that will be used to initialize the aliquoting process
    */
   private function InitiateAliquotingProcess(){
?>
   <div id="aliquot_settings">
      <table>
         <tr><td>Parent Sample Format</td><td><input type='text' id="parent_format" name='aliquot_settings[parent_format]' value='BDT 6' size="15" /></td>
            <td>Aliquot Format</td><td><input type='text' id="aliquot_format" name='aliquot_settings[aliquot_format]' value="AVAQ 5-7,7" size="15" /></td>
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
    * Merges the different aliquot settings that might be defined from the client side
    * 
    * @return  integer  0
    * @todo    Add validation of the data at this point to avoid data validation being scattered all over the code
    */
   private function AliquotingSettings(){
      if(isset($_POST['parent']) && !in_array($_POST['parent'], array('', 'undefined')))
         $this->settings['parent'] = json_decode($_POST['parent'], true);
      
      if(isset($_POST['aliquot2save']) && !in_array($_POST['aliquot2save'], array('', 'undefined')))
         $this->settings['aliquot2save'] = json_decode($_POST['aliquot2save'], true);
      
      if(isset($_POST['settings']) && !in_array($_POST['settings'], array('', 'undefined')))
         $this->settings['settings'] = json_decode($_POST['settings'], true);
      elseif(isset($_POST['aliquot_settings']) && !in_array($_POST['aliquot_settings'], array('', 'undefined')))
          $this->settings['settings'] = $_POST['aliquot_settings'];
      
      $this->settings['searchItem'] = strtoupper($_POST['searchItem']);
      if(isset($_POST['nextTray']) && !in_array($_POST['nextTray'], array('', 'undefined'))) $this->settings['nextTray'] = strtoupper($_POST['nextTray']);
      if(isset($_POST['nextPosition']) && !in_array($_POST['nextPosition'], array('', 'undefined'))) $this->settings['nextPosition'] = strtoupper($_POST['nextPosition']);
      
      $this->settings['presaved_sample'] = false;
      $this->Dbase->CreateLogEntry($this->settings['searchItem'], 'aliquots');    //just log the way we are receiving the searched items
      return 0;
   }

   /**
    * Generates the top panel with the settings currently being used
    *
    * @param   array    $aliq    An array with the data which the user has already defined
    * @return  string   Returns the HTML code that will be used to create this top panel
    */
   private function GenerateTopPanel(){
      if(!isset($this->settings['settings']['aliquot_format2use'])){
         if(strpos($this->settings['settings']['aliquot_format'], ',') !== false){  //expecting aliquots with different formats
            $parts = preg_split('/,/', strtoupper($this->settings['settings']['aliquot_format']));
            $this->settings['settings']['aliquot_format2use'] = '';
            $aliquot_format = '';
            foreach($parts as $t){
               $t = trim($t);
               $tparts = preg_split('/\s+/', strtoupper($t));
               if(count($tparts) == 1){
                  $first = str_replace('-', ',', $tparts[0]);
                  $af = '[0-9]{'."$first}";
               }
               else{
                  $last = str_replace('-', ',', $tparts[1]);
                  $af = trim($tparts[0]).'[0-9]{'."$last}";
               }
               $this->Dbase->CreateLogEntry("Aliquot Format Parts:\n".print_r($tparts, true), 'debug');
               $aliquot_format .= ($aliquot_format == '') ? '' : '|';
               $aliquot_format .= $af;
            }
         }
         else{
            $parts = preg_split('/\s+/', strtoupper($this->settings['settings']['aliquot_format']));
            $aliquot_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
         }
         $this->settings['settings']['aliquot_format2use'] = $aliquot_format;
      }
      
      if(!isset($this->settings['settings']['parent_format2use'])){
         $parts = preg_split('/\s+/', strtoupper($this->settings['settings']['parent_format']));
         $parent_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
         $this->settings['settings']['parent_format2use'] = $parent_format;
      }
      $no_of_aliquots = $this->settings['settings']['noOfAliquots'];
      $this->Dbase->CreateLogEntry("Parent Format: {$this->settings['settings']['parent_format2use']}", 'debug');
      $this->Dbase->CreateLogEntry("Aliquot Format: {$this->settings['settings']['aliquot_format2use']}", 'debug');

?>
      <div id='aliquot_settings'>
         <table>
            <tr><td>Parent Format:<b><?php echo "{$this->settings['settings']['parent_format']}"; ?></b></td>
               <td>&nbsp;&nbsp;Aliquot Format:<b><?php echo "{$this->settings['settings']['aliquot_format']}"; ?></b></td>
               <td>&nbsp;&nbsp;No of Aliquots:<b><?php echo "$no_of_aliquots"; ?></b></td></tr>
            <tr id='aliquotNos'><td colspan="3"><table>
<?php

         for($i=0; $i < $this->settings['settings']['noOfAliquots']; $i++){
            $t = $this->settings['settings']['trays'][$i];
            if(!isset($t['format2use'])){
               $parts = explode(' ', $t['name']);
               $trayName =  trim($parts[0]).'[0-9]{'."$parts[1]}";
               $this->settings['settings']['trays'][$i]['format2use'] = $trayName;
               $this->settings['settings']['trays'][$i]['just_trays'] = $t['name'];
            }
?>
            <tr><td>Tray:<b><?php echo "{$t['name']}"; ?></b></td>
            <td>Description:<b><?php echo "{$t['descr']}"; ?></b></td>
            <td>Size:<b><?php echo "{$t['size']}"; ?></b></td></tr>
<?php
         }
echo "
         </table></td></tr>
         </table>
      </div>
";
      }

   /**
    * Generates the bottom panel, which comprises the current parent sample settings as well as the current aliquots. The server variables/ settings
    * are passed to the client at this point
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
         if(is_string($res)){
            $this->settings['aliquot2save'] = array();
            $addinfo = $res;
         }
         else{
            if(preg_match('/^' . $this->settings['settings']['parent_format2use'] . '$/i', $this->settings['searchItem'])){
               $res = $this->IsParentSample();
               if(is_string($res)) $addinfo = $res;
               else{
                  $aliquots = $res;
                  $this->settings['currentAliquots'] = array();
//                  $this->Dbase->CreateLogEntry("Parented samples:\n".print_r($res, true), 'debug');
//                  $this->Dbase->CreateLogEntry("Parented samples settings:\n".print_r($this->settings, true), 'debug');
               }
            }
            elseif(preg_match("/^".$this->settings['settings']['aliquot_format2use']."$/i", $this->settings['searchItem'])){
               //check if we have the parent sample defined for this
//               $this->Dbase->CreateLogEntry("Settings when having an aliquot:\n".print_r($this->settings, true), 'debug');
               if(!isset($this->settings['parent']['label'])){
                  $addinfo = 'Error! There is no parent sample defined for this aliquot, <b>please scan a parent sample first!</b>';
               }
               else $aliquots = $res;
            }
            else{
               $addinfo = 'Unrecognized Sample. Try again';
               //clear all the parent and aliquot fields. jst remain with the searched sample field
//               $this->Dbase->CreateLogEntry("Unrecognised sample:\n".print_r($this->settings, true), 'debug');
               $this->settings['parent'] = array();
               $this->settings['aliquot2save'] = array();
               $aliquots = array();
            }
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
      $parent = (!isset($this->settings['parent'])) ? 'undefined' : json_encode($this->settings['parent']);
      $aliquot2save = (!isset($this->settings['aliquot2save'])) ? 'undefined' : json_encode($this->settings['aliquot2save']);
      $settings = json_encode($this->settings['settings']);
      
//      $this->Dbase->CreateLogEntry("Settings being saved:\n".print_r($this->settings, true), 'debug');
      if(count($aliquots) == 0 && count($this->settings['currentAliquots']) != 0) $aliquots = $this->settings['currentAliquots'];
      
echo <<<Content
   <div id="search_form">
      <span id="search_mssg">
      <div class='$errorClass'>
         $addinfo<br />$searchedSample
      </div>
      </span><br />
      <input type="text" name="searchItem" size="15px" id="searchItemId" value=""/>
      <input type="button" name="find" id="submitId" value="FIND" />
      <div id='search_sort_results'>
         <div id='animal_data'>
            <table>
               <tr><td colspan='2'>Animal Id: <b>{$this->settings['parent']['animal']}</b></td><td colspan='2'>Source Sample: <b>{$this->settings['parent']['label']}</b></td></tr>
               <tr class='last_row'><td colspan='2'>Organism: <b>{$this->settings['parent']['organism']}</b></td><td colspan='2'>Comments: <b>{$this->settings['parent']['comment']}</b></td></tr>
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
      for($i = 0; $i < $this->settings['settings']['noOfAliquots']; $i++){
         $predTray = $this->settings['settings']['trays'][$i];
         if(isset($aliquots[$i]['aliquot_number'])){
            /**
             * if the aliquot is already defined, display the institution its intended to go to by getting the tray name using the aliquot number
             * and tray indexes. Add this fetched tray to the list of assignedTrays
             */
            //$predTray = $this->settings['settings']['trays'][$aliquots[$i]['aliquot_number'] - 1];
         }
         else{
            /**
             * naturally in case an aliquot is not defined, we would get the next institution to receive an aliquot. However if an aliquot of an
             * insitution with a lower index is deleted, the institution with higher indexes might already have received an aliquot. To avoid this
             * check which insitution is not on the list of assignedTrays and get the first one which is not defined.
             */
            //$t = array_diff($this->settings['just_trays'], $assignedTrays);
            //$predTray = $this->settings['settings']['trays'][key($t)];
         }
         //$assignedTrays[] = $predTray['name'];
         if($i == $this->settings['settings']['noOfAliquots'] - 1){
            $bClass = "class='bottom'";
            $addClass = 'bottom';
         }
         if($aliquots[$i]['tray'] == '') $aliquots[$i]['tray'] = '&nbsp;';
         if($aliquots[$i]['position'] == '') $aliquots[$i]['position'] = '&nbsp;';
         $actions = ($aliquots[$i]['label'] == '') ? '&nbsp;' : "<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"edit\")'>Edit</a>&nbsp;<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"delete\")'>Delete</a>";
         if($aliquots[$i]['label'] == '') $aliquots[$i]['label'] = '&nbsp;';
         $presaved = ($this->settings['presaved_sample'] == true && $aliquots[$i]['label'] == $this->settings['searchItem']) ? 'presaved' : '';
         echo "<tr class='$presaved'><td class='left $addClass'>" . $predTray['descr'] . ":</td><td $bClass>{$aliquots[$i]['label']}" .
                 "</td><td $bClass>{$aliquots[$i]['tray']}</td><td $bClass>{$aliquots[$i]['position']}</td><td $bClass>$actions</td></tr>";
      }
?>
         </table>
      </div>
   </div>
</div>
<script type="text/javascript">
   Samples.parent = <?php echo $parent; ?>;
   Samples.settings = <?php echo $settings; ?>;
   Samples.aliquot2save = <?php echo $aliquot2save; ?>;
   $('#submitId').bind('click', Samples.search);
   $('#searchItemId').bind('keyup', Samples.simulateEnterButton);
</script>
<?php
   }
   
   /**
    * Generates the lower panel that shows the searched sample details as well as where an aliquot should be or is stored
    *
    * @param   string   $sample  (Optional) The sample that we want to check if its a parent sample
    * @return  mixed    returns a string with the error message if there was an error else return an array with the parent sample metadata
    */
   private function IsParentSample($sample = ''){
//      $this->Dbase->CreateLogEntry(print_r($this->settings, true), 'debug');
      //try and see that it is in the db before making it the parent sample
      $sample = ($sample == '') ? $this->settings['searchItem'] : $sample;
      $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location, a.comment, b.organism from aliq_samples as a inner join aliq_animals as b on a.animal_id=b.id "
              . "where lower(a.label) = lower('$sample')";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      //LogError();
      if($results == 1) return "There was an error while fetching data from the database.";
      elseif(count($results) == 0) return "The sample $sample was not found in the database.";
      elseif(count($results) != 1) return "There is an error in the database. There can be only one sample with this id.";
      else{
         //check if we have some aliquots from this sample
         $this->settings['parent'] = array(
             'label' => $sample,
             'comment' => mysql_real_escape_string($results[0]['comment']),
             'organism' => $results[0]['organism'],
             'animal' => $results[0]['animal_id']
         );

         //check if there are other aliquots from this sample
         $this->Dbase->query = "select a.* from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='$sample' order by a.aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         return $aliq;
      }
   }
   
   /**
    * Initiates the saving of the sample which was previously scanned and then determines the saving parameters of the current aliquot
    * 
    * @return  mixed    Returns a string with the error message in case there was an error, else it returns an array with the current aliquot settings
    */
   private function CurrentSampleSettings(){
      //check whether we have all the parent sample metadata
      if( isset($this->settings['parent']['label']) && !in_array($this->settings['parent']['label'], array('', 'undefined')) ){
         //check whether there is some saving that should take place before looking for other things
         $res = $this->SaveAliquot();
         if(is_string($res)) return $res;
         //get the metadata for this sample, ie from which animal its coming from and the animal metadata
         $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location from aliq_samples as a inner join aliq_animals as b on a.animal_id=b.id "
                 . "where lower(a.label) like lower('{$this->settings['parent']['label']}')";
//         $this->Dbase->CreateLogEntry('', 'debug', true);
         $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($results == 1) return "There was an error while fetching data from the database.";
         elseif(count($results) > 1){ //we have more than one sample in the dbase. THIS IS A HUGE ERROR AS WE ARE ONLY DEALING WITH UNIQUE SAMPLES
            return "There is an error in the database. There can be only one sample with this id.";
         }
         elseif(count($results) == 0) return 'The sample is not in the database';   //the sample is not in the dbase
         elseif(count($results) == 1){   //we have a hit, the sample is in the dbase
            //check that the aliquot aint saved before
            $this->Dbase->query = "select a.*, b.label as parent from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id
            where a.parent_sample = (select parent_sample from aliquots where label='{$this->settings['searchItem']}') 
            order by a.aliquot_number";
            $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
            if($aliq == 1) return "There was an error while fetching data from the database.";
            elseif(count($aliq) != 0){
               //this sample is already saved!
               $this->settings['currentAliquots'] = $aliq;
               $this->settings['presaved_sample'] = true;
               //fetch the parent sample metadata from the db
               $this->IsParentSample($aliq[0]['parent']);
//               $this->Dbase->CreateLogEntry("Settings:\n".print_r($this->settings['currentAliquots'], true), 'debug');
               return "Error! The sample <b>{$this->settings['searchItem']}</b> has already been saved before.";
            }
            $animal = $results[0]['animal_id'];
            //check if there are other aliquots from this sample
            $this->Dbase->query = "select a.* from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='{$this->settings['parent']['label']}' order by a.aliquot_number";
            $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
            if($aliq == 1) return "There was an error while fetching data from the database.";
            //determine which aliquot number we really want. will take care if we delete an aliquot of a institution defined first
            //expects to find a matching set of noOfAliquots and the index. In case this match is inconsistent,
            //create an generate a position to correct this
            $generated = false;
            $this->settings['aliquot2save'] = array();
            for($i = 0; $i < $this->settings['noOfAliquots']; $i++){
               if($i + 1 != $aliq[$i]['aliquot_number']){
                  $generated = true;
                  $res = $this->NewNextSlot($i);
                  if(is_string($res)) return $res;
                  break;
               }
            }
            if($generated == false) $this->NewNextSlot(count($aliq));
            $this->settings['aliquot2save']['label'] = $this->settings['searchItem'];
            //now create the place holder for this new sample
            $tray = "<input type='text' name='nextTray' size='15px' id='nextTrayId' value='{$this->settings['aliquot2save']['nextBox']}' />";
            $position = "<input type='text' name='nextPosition' size='15px' id='nextPositionId' value='{$this->settings['aliquot2save']['nextPosition']}' />";
            $aliq[count($aliq)] = array('label' => $this->settings['searchItem'], 'tray' => $tray, 'position' => $position);
            return $aliq;
         }
      }
   }
   
   /**
    * Saves an aliquot to the database. Performs the integrity checks first before saving the aliquot
    * 
    * @return  mixed    Returns 0 if all goes ok, else it returns a string with a message of the error that has occurred.
    */
   private function SaveAliquot(){
      $save_position = $this->settings['aliquot2save']['nextPosition'];
      $save_tray = $this->settings['aliquot2save']['nextBox'];
      $prev_sample = $this->settings['aliquot2save']['label'];
      $aliquotIndex = $this->settings['aliquot2save']['aliquotIndex'];
      $aliqNo = $aliquotIndex + 1;
      $tray_size = $this->settings['settings']['trays'][$aliquotIndex]['size'];
      $trayFormat = $this->settings['settings']['trays'][$aliquotIndex]['format2use'];
      $parentSample = $this->settings['parent']['label'];
//      $this->Dbase->CreateLogEntry("Settings while saving:\n".print_r($this->settings, true), 'debug');
      
  /*    
      $this->Dbase->CreateLogEntry(
         "Aliquot Format => {$this->settings['aliquot_format2use']}
        Previous Sample => $prev_sample
        Tray Format => {$this->settings['format2use'][$aliquotIndex]}
        Save in Tray => $save_tray
        Save Position => $save_position
        Aliquot Index => $aliquotIndex", 'debug'
      );
*/
      //we have something to save, so save it, bt first confirm that the aliquot is right and the tray is ok
      if(preg_match('/^' . $this->settings['settings']['aliquot_format2use'] . '$/i', $prev_sample) && preg_match('/^' . $trayFormat . '$/i', $save_tray) && is_numeric($save_position) 
              && is_numeric($aliquotIndex)){
         
         //check if there are other aliquots from this sample
         $this->Dbase->query = "select a.* from aliquots as a inner join aliq_samples as b on a.parent_sample=b.id where b.label='$parentSample' order by a.aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         elseif(count($aliq) + 1 > $this->settings['settings']['noOfAliquots']){
            //if there are enough aliquots it means we dont need to add another aliquot
//            $this->Dbase->CreateLogEntry("Settings when > 3 aliqs:\n".print_r($this->settings, true), 'debug');
            return "Error! A sample can only have {$this->settings['settings']['noOfAliquots']} aliquots.";
         }
//      echo '<pre>'.print_r($this->settings, true).'</pre>';
         if($save_position > 0 && $save_position <= $tray_size){
            $parentSampleId = $this->Dbase->GetSingleRowValue('aliq_samples', 'id', 'label', $parentSample);
            if($parentSampleId == -2){
               return "There was an error while fetching data from the database.";
            }
            else{
               $this->settings['currentAliquots'] = $aliq;
               $cols = array('label', 'parent_sample', 'aliquot_number', 'tray', 'position');
               $colvals = array(strtoupper($prev_sample), $parentSampleId, $aliqNo, $save_tray, $save_position);
               $results = $this->Dbase->InsertData("aliquots", $cols, $colvals);
               if($results == 1) return "There was an error while saving the aliquot <b>$prev_sample</b>.";
               return 0;
               //echo "save this $save_aliquot $save_position $save_tray";
            }
         }
         else return "Unable to save <b>$prev_sample($aliqNo)</b> in tray <b>$save_tray</b> at position <b>$save_position</b>.";
      }
      else return 0;
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
//      $this->Dbase->CreateLogEntry("Aliquot Index: $aliquotIndex", 'debug');
//      $this->Dbase->CreateLogEntry("Settings:\n".print_r($this->settings['settings']['trays'], true), 'debug');
      $trayType = strtolower($this->settings['settings']['trays'][$aliquotIndex]['format2use']);  //type must be from 1-4
      $pat = "and lower(b.label) rlike '^" . strtolower($this->settings['settings']['parent_format2use']) . "$'";

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
         $nextBox = $trayPrefix . str_repeat('0', $intCount - 1) . '1';
         $nextPosition = 1;
      }
      else{
         if($results[0]['position'] == $this->settings['settings']['trays'][$aliquotIndex]['size']){
            $nextInt = (substr($results[0]['tray'], -$intCount) + 1);
            $nextBox = $trayPrefix . str_repeat('0', $intCount - strlen($nextInt)) . $nextInt;
            $nextPosition = 1;
         }
         else{
            $nextBox = $results[0]['tray'];
            $nextPosition = $results[0]['position'] + 1;
         }
      }
      $this->settings['aliquot2save']['nextBox'] = $nextBox;
      $this->settings['aliquot2save']['nextPosition'] = $nextPosition;
      $this->settings['aliquot2save']['aliquotIndex'] = $aliquotIndex;
      return 0;
   }

   /**
    * Creates a dbase dump of the current database and forces a download.
    * 
    * @todo    Handle the error cases well. If there is an error, an incorrect download will still be forced to the user
    */
   private function CreateDbDump(){
      $res = $this->CreateDbSnapshot($this->config, 'dbase_dumps', 'user_initiated');
      if(is_string($res) || !file_exists($res[0])){
         $this->Dbase->CreateLogEntry($res, 'fatal');
         //since we are having an error and this was an ajax call, we shall have to recreate the interface again thru a thrupass call
         $fd = fopen($_SERVER['HTTP_REFERER'], 'r');
         fpassthru($fd);
//            $this->Home('There was an error while generating a current database dump. Please do it manually');
      }
      else{    //lets send the file back to the user
         header('Content-Description: File Transfer');
         header('Content-Type: application/octet-stream');
         header('Content-Disposition: attachment; filename=' . basename($res[0]));
         header('Content-Transfer-Encoding: binary');
         header('Expires: 0');
         header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
         header('Pragma: public');
         header('Content-Length: ' . filesize($res[0]));
         ob_clean();
         flush();
         readfile($res[0]);
         exit;
      }
   }
}
?>
