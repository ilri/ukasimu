<?php
class Aliquots extends DBase{

   /**
    * @var Object An object with the database functions and properties. Implemented here to avoid having a million & 1 database connections
    */
   public $Dbase;

   /**
    * @var HTML_String  The links that will appear at the bottom of the page
    */
   public $footerLinks;

   public $addinfo;

   public $links;
   
   private $nextBox;
   
   private $nextPosition;
   
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
   
   public function TrafficController(){
      if(OPTIONS_REQUESTED_MODULE == '') $this->HomePage();
      elseif(OPTIONS_REQUESTED_MODULE == 'sort_aliquots') $this->SampleProcessing();
   }
   
   /**
    * Creates the home page for the system.
    * 
    * @param   string   $addinfo    Any additional information that needs to be displayed on the home page
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
    * The main search and sort function. searches for the searched item and if it is an aliquot determines its position and tray. if its a parent
    * sample it sav$noOfAliquotses it. It also receives previous values that are automatically saved
    *
    * @param   string $add_message       An optional string with a message to be displayed
    * @global string $nextBox           The tray label to save the aliquot to
    * @global integer $nextPosition     The position in the next tray to save to
    * @return <type>
    */
   private function SampleProcessing($add_message = ''){
      $addinfo = '';
      echo '<form name="form" action="?page=sort_aliquots" method="POST" id="searchFormId">';
      //check the aliquoting settings
      if($this->AliquotingSettings() == 1){
         echo "</form>";   //close the form and return
         return;
      }
      
      //check whether there is some saving that should take place before looking for other things
      $res = $this->SaveAliquot();
      if(is_string($res)){
         $curData['addinfo'] = $res;
         $this->settings['save_position'] = '';
         $this->settings['save_tray'] = '';
      }
      
      $curData = array(
          'errorClass' => 'error',
          'parentSampleId' => ''
      );
      if(isset($this->settings['searchItem']) && !in_array($this->settings['searchItem'], array('', 'undefined'))){
//         $this->CreateLogEntry(print_r($this->settings, true));
         //we have a sample, check if it is an aliquot or a real sample
         if(preg_match("/^".$this->settings['aliquot_format2use']."$/i", $this->settings['searchItem'])){
            $res = $this->IsSavedAliquot();
            if(is_string($res)){
               $addinfo = $res;
               $aliq = array();
            }
            else $aliq = $res;
         }
         elseif(preg_match('/^' . $this->settings['parent_format2use'] . '$/i', $this->settings['searchItem'])){
            $res = $this->IsParentSample();
            if(is_string($res)){
               $addinfo = $res;
               $aliq = array();
            }
            elseif($res == 0) $aliq = array();
            else $aliq = $res;
         }
         else{
            $addinfo = 'Unrecognized Sample. Try again';
         }
         $curData['addinfo'] = $addinfo;
         $this->GenerateLowerPanel($curData, $aliq);
      }
      else{
         $addinfo = 'Please enter the sample label to search for.';
         $curData['errorClass'] = 'no_error';
      }
      if($addinfo == '' || !isset($addinfo)){
         $addinfo = 'Enter or scan the aliquot you want to sort.';
         $curData['errorClass'] = 'no_error';
      }
      
      $curData['addinfo'] = $addinfo;
   }

   /**
    * Checks and verifies the aliquot settings to be used in this session. if the settings are not set, it sets them
    *
    * @return  integer    Returns 0 when all is set ok, and 1 when the settings are not set
    */
   private function AliquotingSettings(){
//      echo '<pre>' . print_r($_POST, true) . '</pre>';
      if(isset($_POST['aliquot_settings']) && is_array($_POST['aliquot_settings'])){
         $this->settings = array_merge($_POST['aliquot_settings'], 
            array(
               'searchItem' => strtoupper($_POST['searchItem']),
               'parent_sample' => $_POST['parent'],
               'animal' => $_POST['curAnimal'],
               'save_tray' => $_POST['nextTray'],
               'save_position' => $_POST['nextPosition'],
               'prev_sample' => $_POST['prevSample'],
               'aliquot_index' => $_POST['aliquot_number']
            )
         );
         $this->CreateLogEntry(print_r($this->settings, true));
         $addinfo = '';
         if(!isset($this->settings['parent_format']) || $this->settings['parent_format'] == '') $addinfo = "Please enter the parent sample format.";
         if(!isset($this->settings['aliquot_format']) || $this->settings['aliquot_format'] == '') $addinfo = "Please enter the aliquout format.";
         if(!is_numeric($this->settings['aliquot_number'])) $addinfo = "Please enter the number of aliquots";
         if(!isset($this->settings['trays']) || !is_array($this->settings['trays'])) $addinfo = "Please enter the tray details.";
         if($addinfo != ''){
            //something is missing, so offer to recreate everything again
            ?>
               <div id="aliquot_settings">
                  <div id="addinfo"><?php echo $addinfo; ?></div>
               <table>
                  <tr><td>Parent Sample Format</td><td><input type='text' name='aliquot_settings[parent_format]' size="15" /></td>
                     <td>Aliquot Format</td><td><input type='text' name='aliquot_settings[aliquot_format]' size="15" /></td>
                     <td>No of Aliquots</td><td><input type='text' name='aliquot_settings[aliquot_number]' size="3" id='aliquot_number_id' /></td></tr>
                  <tr id='aliquotNos'>&nbsp;</tr>
               </table>
                  <div id='links'><input type='button' name='find' value='Save' onclick="Samples.search();" />
                      <input type='button' name='cancel' value='Cancel' /></div>
               </div>
               <script type="text/javascript">
                  $('#aliquot_number_id').bind('blur', Samples.generateAliquots);
               </script>
            <?php
            return 1;   //there is an error in the defined settings. so this is the end of this thread
         }
         else{ //we have everything ok, so lets hide them, so to be used with the next request
            $this->GenerateTopPanel();
            return 0;
         }
      }
      else{ //we dont have the settings, create the interface for the settings
         $this->InitiateAliquotingProcess();
         return 1;   //not really an error, but we return 1 to trick the system in closing the form and the user to proceed adding other data
      }
      //print_r($_POST);
      return 0;
   }
   
   /**
    * Saves an aliquot to the database. Does the integrity checks first before saving the aliquot
    * 
    * @return  mixed    Returns 0 if all goes ok, else it returns a string with a message of the error that has occurred.
    */
   private function SaveAliquot(){
      $save_position = $this->aliqutSettings['save_position'];
      $save_tray = $this->aliqutSettings['save_tray'];
      $prev_sample = $this->aliqutSettings['prev_sample'];
      $aliquot_number = $this->aliqutSettings['aliquot_number'];
      if(preg_match('/^' . $this->settings['aliquot_format2use'] . '$/i', $prev_sample, $res) && isset($save_tray) && $save_tray != '' && is_numeric($save_position) 
              && is_numeric($aliquot_number) && $addinfo == ''){
         //we have something to save, so save it, bt first confirm that the aliquot is right
//      echo '<pre>'.print_r($this->settings, true).'</pre>';
//      echo "{$this->settings['trays_format2use'][$aliquot_number-1]} -- $save_tray<br />";
         if(preg_match('/^' . $this->settings['trays_format2use'][$aliquot_number - 1] . '$/i', $save_tray, $res) &&
                 ($save_position > 0 && $save_position <= $this->settings['trays'][$aliquot_number - 1]['size'])){
            $parentSampleId = GetSingleRowValue("export_samples", 'id', array('label'), array($parent_sample));
            if($parentSampleId == -2){
               return "There was an error while fetching data from the database.$contact";
            }
            else{
               $cols = array('label', 'parent_sample', 'aliquot_number', 'tray', 'position');
               $colvals = array(strtoupper($prev_sample), $parentSampleId, $aliquot_number, $save_tray, $save_position);
               $results = InsertValues("aliquots", $cols, $colvals);
               if(is_string($results)) return "There was an error while saving the aliquot $prev_sample.";
               return 0;
               //echo "save this $save_aliquot $save_position $save_tray";
            }
         }
         else{
            return "Unable to save $prev_sample($aliquot_number) in tray $save_tray at position $save_position.";
         }
      }
      return 0;
   }
   
   private function IsSavedAliquot(){
      //check if this aliquot has been saved before         
      $this->Dbase->query = "select a.parent_sample, b.label, c.animal_id from aliquots as a inner join export_samples as b on a.parent_sample=b.id "
              . "inner join export_animals as c on b.animal_id=c.id where a.label='{$this->settings['searchItem']}'";
      $results = $this->Dbase->GetQueryValues(MYSQL_ASSOC);
      if($results == 1) return "There was an error while fetching data from the database.";
      elseif(count($results) > 0){
         $this->Dbase->query = "select * from aliquots where parent_sample='" . $results[0]['parent_sample'] . "' order by aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         else{
            $animal = $results[0]['animal_id'];
            $parent_sample = $results[0]['label'];
            return "The aliquot '$this->settings['searchItem']' has already been saved.";
         }
         $this->settings['save_position'] = '';
         $this->settings['save_tray'] = '';
      }
      elseif(isset($parent_sample) && $parent_sample != 'undefined'){
         if($addinfo == ''){ //we dont have an error
            //get the metadata for this sample, ie from which animal its coming from and the animal metadata
            $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location from export_samples as a inner join export_animals as b on a.animal_id=b.id "
                    . "where lower(a.label) like lower('$parent_sample')";
            $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
            if($results == 1) return "There was an error while fetching data from the database.";
            elseif(count($results) > 1){ //we have more than one sample in the dbase. THIS IS A HUGE ERROR AS WE ARE ONLY DEALING WITH UNIQUE SAMPLES
               return "There is an error in the database. There can be only one sample with this id.";
            }
            elseif(count($results) == 0) return 'The sample is not in the database';   //the sample is not in the dbase
            elseif(count($results) == 1){   //we have a hit, the sample is in the dbase
               //check if there are other aliquots from this sample
               $this->Dbase->query = "select * from aliquots where parent_sample=" . $results[0]['id'] . " order by aliquot_number";
               $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
               if($aliq == 1) return "There was an error while fetching data from the database.";
               elseif(count($aliq) > $this->settings['aliquot_number'] - 1){
                  //if there are enough aliquots it means we dont need to add another aliquot
                  return "Error! A sample can only have " . $this->settings['aliquot_number'] . " aliquots.";
               }
               else{
                  $animal = $results[0]['animal_id'];
                  $curAliquotNo = null;
                  //determine which aliquot number we really want. will take care if we delete an aliquot of a institution defined first
                  //expects to find a matching set of aliquot_number and the index. In case this match is inconsistent,
                  //create an generate a position to correct this
                  $generated = false;
                  for($i = 0; $i < $noOfAliquots; $i++){
                     if($i + 1 != $aliq[$i]['aliquot_number']){
                        $generated = true;
                        $this->NewNextSlot($i + 1);
                        $curAliquotNo = $i + 1;
                        break;
                     }
                  }
                  if($generated == false) $this->NewNextSlot(count($aliq) + 1);
                  //now create the place holder for this new sample
                  $tray = "<input type='text' name='nextTray' size='15px' id='nextTrayId' value='$nextBox' />";
                  $position = "<input type='text' name='nextPosition' size='15px' id='nextPositionId' value='$nextPosition' />"
                          . "<input type='hidden' size='15px' name='saveAliquot' id='saveAliquotId' value='$this->settings['searchItem']' />"
                          . "<input type='hidden' size='15px' name='aliquot_number' id='aliquotNumberId' value='$curAliquotNo' />";
                  $aliq[count($aliq)] = array('label' => $this->settings['searchItem'], 'tray' => $tray, 'position' => $position);
                  $this->aliqutSettings['save_tray'] = $nextBox;
                  $this->settings['save_position'] = $nextPosition;
                  return $aliq;
               }
            }
         }
      }
      else{
         return 'We have an aliquot but no parent sample';
      }
   }

   /**
    * Calculates the next empty slot in the next box. This is the position where the current aliquot will be placed
    *
    * @param   string   $searchId
    * @param   integer  $aliquot_number
    * @return  integer  Returns 0 on success, else returns 1
    */
   private function NewNextSlot($aliquot_number){
      $trayType = strtolower($aliquot_settings['trays_format2use'][$aliquot_number - 1]);  //type must be from 1-4
      $pat = "and lower(b.label) rlike '^" . strtolower($aliquot_settings['parent_format2use']) . "$'";
      $this->Dbase->query = "select a.tray, a.position from aliquots as a inner join export_samples as b on a.parent_sample=b.id "
              . "where a.tray is not null and a.position is not null and lower(a.tray) rlike '^$trayType$' $pat order by a.tray desc, a.position desc limit 0,1";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      //LogError();
      if($results == 1){
         $this->SearchSortResults($searchId, "There was an error while fetching data from the database.");
         return 1;
      }
      preg_match('/^[a-z]+/i', $trayType, $res);
      $trayPrefix = strtoupper($res[0]);
      preg_match('/\{[0-9]+\}/i', $trayType, $res);
      preg_match('/[0-9]+/', $res[0], $res);
      $intCount = $res[0]; //echo $intCount;
      if($results[0]['tray'] == NULL || $results[0]['position'] == NULL){
         $this->nextBox = $trayPrefix . str_repeat('0', $intCount - 1) . '1';
         $this->nextPosition = 1;
      }
      else{
         if($results[0]['position'] == $this->settings['trays'][$aliquot_number - 1]['size']){
            $nextInt = (substr($results[0]['tray'], -$intCount) + 1);
            $this->nextBox = $trayPrefix . str_repeat('0', $intCount - strlen($nextInt)) . $nextInt;
            $this->nextPosition = 1;
         }
         else{
            $this->nextBox = $results[0]['tray'];
            $this->nextPosition = $results[0]['position'] + 1;
         }
      }
      return 0;
   }

   /**
    * creates the output interface after sorting the aliquots. determines automagically where an aliquot ought to be placed and its corresponding location
    *
    * @global <array> $config    Contains the database connection preferences
    * @global <string> $query      The global query string, will be used to log queries
    * @global <string> $contact    A contact message should anything go wrong
    * @global <string> $nextBox    The next tray in sequence to place the aliquot
    * @global <integer> $nextPosition  The next position in the nextbox
    * @param <string> $searchId    The aliquot id we are searching for
    * @param <string> $addinfo     Any additional information that we might want to display
    * @return <none>    Returns nothing. On successful completion it echoes the created contents
    */
   private function SearchSortResults($addinfo=''){
      global $config, $query, $contact, $nextBox, $nextPosition;
      $res = Connect2DB($config);
      if(is_string($res)){
         $this->SearchSortHome("There was an error while connecting to the database.$contact");
         return;
      }
      $this->Dbase->query = "select * from aliquots where SourceID=(select SourceID from aliquots where AliquotID = '{$this->settings['searchItem']}') order by AliquotNo";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      //LogError();
      if($results == 1){
         $this->SearchSortHome("There was an error while fetching data from the database.$contact");
         return;
      }

      $this->SearchSortHome($addinfo);
      
      
?>
   <div id='search_sort_results'>
      <div id='animal_data'>
         <table>
<?php
      if($results[0]['AnimalID'] == NULL) $results[0]['AnimalID'] = 'Error';
?>
            <tr>
               <td colspan='2'>Animal Id: <b><?php echo "{$results[0]['AnimalID']}"; ?></b></td>
               <td colspan='2'>Source Sample: <b><?php echo "{$results[0]['SourceID']}"; ?></b></td>
            </tr>
         </table>
      </div>
      <div id='aliquots'>
         <form name="form" action="?page=update_positions" method="POST">
         <table cellspacing='0'>
            <tr><th class='left'>Destination</th><th>Aliquot</th><th>TrayId</th><th>Position in Tray</th></tr>
<?php

      $submit = false; //do we create the submit button or not
      $box = "<input type='text' name='box' size='15px' value='$nextBox' />";
      $position = "<input type='text' name='position' size='15px' value='$nextPosition' />";
      

      if(isset($results[3]['box']) && isset($results[3]['position'])){ //the aliquot has its place already
         if(strcasecmp($results[3]['AliquotID'], $searchId) == 0) $class = 'kemri_aliquots';
         else $class='';
         $box = $results[3]['box'];
         $position = $results[3]['position'];
      }
      elseif(!isset($results[3]['box']) && !isset($results[3]['position']) && strcasecmp($results[3]['AliquotID'], $searchId) == 0){ //we should assign a place to the aliquot
         $class = 'kemri_aliquots';
         $submit = true;
         $res = NextSlot($searchId, 3);
         if($res) return;
         $box = "<input type='text' name='box' size='15px' value='$nextBox' />";
         $position = "<input type='text' name='position' size='15px' value='$nextPosition' />";
      }
      else{
         $class = '';
         $box = '&nbsp;';
         $position = '&nbsp;';
      }
      if(isset($results[3])){
         echo "<tr class='$class'><td class='left'>KEMRI:</td><td>" . $results[3]['AliquotID'] . " (" . $results[3]['AliquotNo'] . ")</td><td>$box</td><td>$position</td></tr>";
      }

//ILRI aliquots
      if(isset($results[1]['box']) && isset($results[1]['position'])){ //the aliquot has its place already
         if(strcasecmp($results[1]['AliquotID'], $searchId) == 0) $class = 'ilri_aliquots';
         else $class='';
         $box = $results[1]['box'];
         $position = $results[1]['position'];
      }
      elseif(!isset($results[1]['box']) && !isset($results[1]['position']) && strcasecmp($results[1]['AliquotID'], $searchId) == 0){ //we should assign a place to the aliquot
         $class = 'ilri_aliquots';
         $submit = true;
         $res = NextSlot($searchId, 2);
         if($res) return;
         $box = "<input type='text' name='box' size='15px' value='$nextBox' />";
         $position = "<input type='text' name='position' size='15px' value='$nextPosition' />";
      }
      else{
         $class = '';
         $box = '&nbsp;';
         $position = '&nbsp;';
      }
      echo "<tr class='$class'><td class='left'>ILRI:</td><td>" . $results[1]['AliquotID'] . " (" . $results[1]['AliquotNo'] . ")</td><td>$box</td><td>$position</td></tr>";

//ICIPE aliquots
      if(isset($results[0]['box']) && isset($results[0]['position'])){ //the aliquot has its place already
         if(strcasecmp($results[0]['AliquotID'], $searchId) == 0) $class = 'icipe_aliquots';
         else $class='';
         $box = $results[0]['box'];
         $position = $results[0]['position'];
      }
      elseif(!isset($results[0]['box']) && !isset($results[0]['position']) && strcasecmp($results[0]['AliquotID'], $searchId) == 0){ //we should assign a place to the aliquot
         $class = 'icipe_aliquots';
         $submit = true;
         $res = NextSlot($searchId, 1);
         if($res) return;
         $box = "<input type='text' name='box' size='15px' value='$nextBox' />";
         $position = "<input type='text' name='position' size='15px' value='$nextPosition' />";
      }
      else{
         $class = '';
         $box = '&nbsp;';
         $position = '&nbsp;';
      }
      echo "<tr class='$class'><td class='left'>ICIPE:</td><td>" . $results[0]['AliquotID'] . " (" . $results[0]['AliquotNo'] . ")</td><td>$box</td><td>$position</td></tr>";

//KARI aliquots
      if(isset($results[2]['box']) && isset($results[2]['position'])){ //the aliquot has its place already
         if(strcasecmp($results[2]['AliquotID'], $searchId) == 0)
            $class = 'kari_aliquots';
         else
            $class='';
         $box = $results[2]['box'];
         $position = $results[2]['position'];
      }
      elseif(isset($results[2]['box']) == NULL && isset($results[2]['position']) == NULL && strcasecmp($results[2]['AliquotID'], $searchId) == 0){ //we should assign a place to the aliquot
         $class = 'kari_aliquots';
         $submit = true;
         $res = NextSlot($searchId, 4);
         if($res)
            return;
         $box = "<input type='text' name='box' size='15px' value='$nextBox' />";
         $position = "<input type='text' name='position' size='15px' value='$nextPosition' />";
      }
      else{
         $class = '';
         $box = '&nbsp;';
         $position = '&nbsp;';
      }


      if($submit){
         echo "<tr class='$class'><td>KARI:</td><td>" . $results[2]['AliquotID'] . " (" . $results[2]['AliquotNo'] . ")</td><td>$box</td><td>$position</td></tr>";
         echo '<tr><td colspan="4" align="center" class="left bottom"><input type="submit" name="find" id="submitId" value="Update" /></td></tr>';
         echo "<input type='hidden' name='aliquot' value='$searchId' />";
      }
      else{
         echo "<tr class='$class'><td class='left bottom'>KEMRI:</td><td class='bottom'>" . $results[2]['AliquotID'] . " (" . $results[2]['AliquotNo'] . ")</td><td class='bottom'>$box</td><td class='bottom'>$position</td></tr>";
      }
      $content = <<<CONTENT
         </table>
       </form>
      </div>
   </div>
CONTENT;
      echo $content;
   }

   /**
    * Creates the searching and sorting home page. to be used in aliquots sorting
    */
   private function SearchSortHome($addinfo=''){
      if($addinfo == "")
         $addinfo = "Enter or scan the aliquot you want to sort.";
?>
   <div id="search_form">
      <form name="form" action="?page=searching_sorting" method="POST">
         <span id="search_mssg"><?php echo "$addinfo"; ?></span><br />
         <input type="text" name="searchItem" size="15px" id="searchItemId" value="" />   <input type="submit" name="find" id="submitId" value="FIND" />
      </form>
   </div>
<?php
   }
   
   /**
    * Creates the interface that will be used to initialize the aliquoting process
    */
   private function InitiateAliquotingProcess(){
?>
   <div id="aliquot_settings">
      <table>
         <tr><td>Parent Sample Format</td><td><input type='text' id="parent_format" name='aliquot_settings[parent_format]' value='BSR 6' size="15" /></td>
            <td>Aliquot Format</td><td><input type='text' id="aliquot_format" name='aliquot_settings[aliquot_format]' value="AVAQ 5" size="15" /></td>
            <td>No of Aliquots</td><td><input type='text' name='aliquot_settings[aliquot_number]' size="3" value="3" id='aliquot_number_id' /></td></tr>
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
    * Generates the top panel with the settings currentrly being used
    *
    * @param   array    $aliq    An array with the data which the user has already defined
    * @return  HTML     Returns the HTML code that will be used to create this top panel
    */
   private function GenerateTopPanel(){
      $this->settings['aliquot_format'] = strtoupper($this->settings['aliquot_format']);
      $parts = explode(' ', $this->settings['aliquot_format']);
      $aliquot_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
      $this->settings['parent_format'] = strtoupper($this->settings['parent_format']);
      $parts = explode(' ', $this->settings['parent_format']);
      $parent_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
      $no_of_aliquots = $this->settings['aliquot_number'];
      $this->settings['parent_format2use'] = $parent_format;
      $this->settings['aliquot_format2use'] = $aliquot_format;
      $this->settings['trays_format2use'] = array();
      $this->settings['just_trays'] = array();

?>
      <div id='aliquot_settings'>
         <table>
            <tr><td>Parent Format:<b><?php echo "{$this->settings['parent_format']}"; ?></b><input type='hidden' name='aliquot_settings[parent_format]' value='<?php echo "{$this->settings['parent_format']}"; ?>' /></td>
               <td>&nbsp;&nbsp;Aliquot Format:<b><?php echo "{$this->settings['aliquot_format']}"; ?></b><input type='hidden' name='aliquot_settings[aliquot_format]' value='<?php echo "{$this->settings['aliquot_format']}"; ?>' /></td>
               <td>&nbsp;&nbsp;No of Aliquots:<b><?php echo "$no_of_aliquots"; ?></b><input type='hidden' name='aliquot_settings[aliquot_number]' value='<?php echo "$no_of_aliquots"; ?>' /></td></tr>
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
    * Generates the lower panel that shows the searched sample details as well as where an aliquot should be stored
    * 
    * @param   array    $curData
    * @param   array    $aliquots 
    */
   private function GenerateLowerPanel($curData, $aliquots){
//      echo '<pre>' . print_r($aliquots, true) . '</pre>';
echo <<<Content
      <div id="search_form">
            <span id="search_mssg">
               {$curData['add_message']}
               <div class='{$curData['errorClass']}'>
                  {$curData['addinfo']}<br />(Searched Sample: <b>{$this->settings['searchItem']}</b>)
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
                  <tr><td colspan='2'>Organism: <b>{$this->settings['organism']}</b></td><td colspan='2'>Comments: <b>{$curData['comments']}</b></td></tr>
                  <tr><td colspan='2'>Animal Id: <b>{$this->settings['animal']}</b></td><td colspan='2'>Source Sample: <b>{$this->settings['parent_sample']}</b></td></tr>
               </table>
            </div>
            <div id='aliquots'>
               <table cellspacing='0'>
                  <tr><th class='left'>Destination</th><th>Aliquot</th><th>TrayId</th><th>Position in Tray</th><th>Actions</th></tr>
Content;
            
      $this->CreateLogEntry(print_r($aliquots, true));
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
//         echo '<pre>'.print_r($assignedTrays['just_trays'], true).'</pre>';
//         echo '<pre>'.print_r($assignedTrays, true).'</pre>';
//         echo '<pre>'.print_r($t, true).'</pre>';
//         echo key($t);
            $predTray = $this->settings['trays'][key($t)];
         }
         $assignedTrays[] = $predTray['name'];
         if($i == count($this->settings['trays']) - 1){
            $bClass = "class='bottom'";
            $addClass = 'bottom';
         }
         if($aliquots[$i]['tray'] == '') $aliquots[$i]['tray'] = '&nbsp;';
         if($aliquots[$i]['position'] == '') $aliquots[$i]['position'] = '&nbsp;';
         if($aliquots[$i]['label'] == '') $aliquots[$i]['label'] = '&nbsp;';
         $actions = "<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"edit\")'>Edit</a>&nbsp;<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliquots[$i]['label']}\", \"delete\")'>Delete</a>";
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
   
   private function IsParentSample(){
      //try and see that it is in the db before making it the parent sample
      $this->Dbase->query = "select a.id, a.label, b.animal_id, b.location, a.comments, b.organism from export_samples as a inner join export_animals as b on a.animal_id=b.id "
              . "where lower(a.label) = lower('{$this->settings['searchItem']}')";
      $results = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
      //LogError();
      if($results == 1) return "There was an error while fetching data from the database.";
      elseif(count($results) == 0){
         $this->settings['parentSampleId'] = '';
         $this->settings['animal'] = '';
         return "The sample $this->settings['searchItem'] was not found in the database.";
      }
      elseif(count($results) != 1){
         return "There is an error in the database. There can be only one sample with this id.";
      }
      else{
         //check if we have some aliquots from this sample
         $this->settings['comments'] = mysql_real_escape_string($results[0]['comments']);
         $this->settings['organism'] = $results[0]['organism'];
         $this->Dbase->query = "select * from aliquots where parent_sample={$results[0]['id']} order by aliquot_number";
         $aliq = $this->Dbase->ExecuteQuery(MYSQLI_ASSOC);
         if($aliq == 1) return "There was an error while fetching data from the database.";
         elseif(count($aliq) == 0)  return 0;  //we dont have any aliquots defined for this sample
         
         $this->settings['parent_sample'] = strtoupper($this->settings['searchItem']);
         $this->settings['animal'] = $results[0]['animal_id'];
         return $aliq;
      }
   }
}
?>