<?php
require_once 'search_samples_config';
require_once '../common/dbase_functions.php';
require_once '../common/general.php';

//global variables
$pageref = $_SERVER['PHP_SELF'];
$queryString=$_SERVER['QUERY_STRING'];
if(isset($_REQUEST['page']) && $_REQUEST['page']!='') {
   $paging=$_REQUEST['page'];
//if(Checks($paging, 7)) exit(ErrorPage("There is an Error in your data."));
}
else $paging='';
if(isset($_POST['flag']) && $_POST['flag']!='') {
   $action=$_POST['flag'];
//if(Checks($action, 13)) exit(ErrorPage("There is an Error in your data."));
}
else $action='';
$data='';
$dbcon='';
$query='';
$footerLinks="<a href='$pageref'>Home</a>";
$nextBox=NULL; $nextPosition=NULL;

//print_r($_POST);

if($paging=='') Home();
elseif($paging=='upload') UploadInterface();    //create the uploading interface
elseif($paging=='save_file') SaveUploads();
elseif($paging=='search') Search();
elseif($paging=='search_sort') SearchSortHome();
elseif($paging=='sort_aliquots') SampleProcessing();
elseif($paging=='searching') {   //get the id that the user has searched with and just check for words that can cause harm
   $searchId=trim($_POST['searchItem']);
   if(Checks($searchId, 20)) {
      if($searchId=="") $mssg="Please enter an Id to search for.";
      else{
         if(!Checks($searchId, '', '^avaq[0-9]{5}$')){
            SearchSortResults(strtoupper($searchId));
            return;
         }
         $mssg="Error in the entered ID. An Id can only have alpha-numeric characters";
      }
      Search($mssg);
      return;
   }
   $searchId=strtoupper($searchId);
   SearchResults($searchId);
}
elseif($paging=='searching_sorting') {
//get the id that the user has searched with and just check for words that can cause harm
   if(isset($_POST['searchItem'])) $searchId=trim($_POST['searchItem']);
   elseif(isset($_GET['searchItem'])) $searchId=trim($_GET['searchItem']);

   if(Checks($searchId, '', '^avaq[0-9]{5}$')) {
      if($searchId=="") $mssg="Please enter or scan an aliquot Id to search for!";
      else{
         if(!Checks($searchId, 20)){
            SearchResults(strtoupper($searchId));
            return;
         }
         $mssg="Error in the entered aliquot Id($searchId).<br />An aliquot Id can only be in the format AVAQxxxxx where x represent a digit.";
      }
      SearchSortHome($mssg);
      return;
   }
   $searchId=strtoupper($searchId);
   SearchSortResults($searchId);
}
elseif($paging=='update_positions') {
   //we wanna update the position of this aliquot
   $aliquot=$_POST['aliquot2edit']; $box=$_POST['edited_tray']; $position=$_POST['edited_position'];
   $mssg='';
   if($box=='') $mssg="Please enter the tray name.";
   elseif($box == '') $mssg="Please enter the tray name where the aliquot will be stored.";
   elseif(!is_numeric($position)) $mssg="Please enter a position in the box to store the aliquot. It should be an integer.";
   //do the integrity checks

   $aliquot=strtoupper($aliquot);
   $box = strtoupper($box);

   $searchId=trim($_POST['searchItem']);
   if($mssg!='') {
      if(Checks($aliquot, '', '^avaq[0-9]{5}$')) SampleProcessing($mssg);
      else SampleProcessing($mssg);
      return;
   }
   elseif($position > 100 || $position < 1){
      SampleProcessing("The position defined is not valid. The box can only hold 100 samples from number 1 to 100.");
      return;
   }
   //SearchSortResults($searchId);
   UpdateAliquotsPosition($aliquot, $box, $position);
}
elseif($paging=='delete_aliquot') DeleteAliquot();
      
if($paging!='') {
   $footerLinks="<a href='$pageref'>Home</a>";
}
else $footerLinks="";

/**
 * Search Home Page: Creates the search home page for searching animals and samples
 */
function Search($addinfo="") {
   if($addinfo=="") $addinfo="Enter the animal id or scan and enter the sample id";
   $content=<<<CONTENT
   <div id="search_form">
      <form name="form" action="$pageref?page=searching" method="POST">
         <span id="search_mssg">$addinfo</span><br />
         <input type="text" name="searchItem" size="15px" id="searchItemId" value="" />   <input type="submit" name="find" id="submitId" value="FIND" />
      </form>
   </div>
CONTENT;
   echo $content;
}

/**
 * SearchResults:  Performs the search for the entered id/barcode on the samples and animal data and displays the received results
 * Checks whether the id is an animal id. if not it fetches the animal id of this sample. Having the animal Id it searches for all the samples from this animal
 *
 * @param <string> $id A string of the searched item. This can be either an animal id or a sample id. so search from the samples table
 */
function SearchResults($id) {
global $config, $query, $contact, $tables;
   $searchBySamples=false; //in case of error it searches by sample else by animal id
   $res=Connect2DB($config);
   if(is_string($res)) {
      Search("There was an error while connecting to the database.$contact");
      return;
   }
   
   //check if its an animal
   $animalId=GetSingleRowValue($tables['samples'], 'AnimalID', 'AnimalId', "%$id%", 'LIKE');
   //LogError();
   if($animalId==-2) {
      Search("There was an error while fetching data from the database.$contact");
      return;
   }
   elseif($animalId=="") {  //we havent found it yet, so check if its a sample
      $animalId=GetSingleRowValue($tables['samples'], 'AnimalID', 'Comments', "%$id%", 'LIKE');
      //LogError();
      if($animalId==-2) {
         Search("There was an error while fetching data from the database.$contact");
         return;
      }
      elseif(is_string($animalId)) {//check that it only belongs to one animal
         $query="select AnimalID from ".$tables['samples']." where AnimalID like '%$animalId%' group by AnimalID";
         $results=GetQueryValues($query, MYSQL_ASSOC);
         if(is_string($results)){
            Search("There was an error while fetching data from the database.$contact");
            return;
         }
         elseif(count($results)!=1){//we have an error as the sample appears in more than one animal
            $searchBySamples=true;
         }
      } //found it
      elseif($animalId=='') {
         Search("There was no matching record(s) for the searched id $id.");
         return;
      }
   }
   elseif(is_string($animalId)) {} //found it
   //*********************INCOMPLETE CODE***********************************
   //get the animal info
   $general=array();
   //********************************************************
   //
   //now do the blast search
   if(!$searchBySamples){
      $query="select a.SampleID, a.AnimalID, a.VisitID, a.VisitDate, a.Longitude, a.Latitude, a.Comments, b.Description, c.name "
          ."from samples as a inner join sample_types_def as b on a.sample_type=b.count inner join contacts as c on a.main_operator=c.count "
          ."where a.AnimalID='$animalId'";
   }
   else{
      $query="select a.SampleID, a.AnimalID, a.VisitID, a.VisitDate, a.Longitude, a.Latitude, a.Comments, b.Description, c.name "
          ."from Samples as a inner join sample_types_def as b on a.sample_type=b.count inner join contacts as c on a.main_operator=c.count "
          ."where a.SampleID='$id'";
   }
   $results=GetQueryValues($query, MYSQL_ASSOC);
   LogError();
   if(is_string($results)) {
      Search("There was an error while fetching data from the database.$contact");
      return;
   }
   //print_r($results);
   //populate the samples array information. Fetch the parent tube from the comments and use it as the key
   $samples=array();
   foreach($results as $t) {
      $temp=substr($t['Comments'],strpos($t['Comments'],"Source Tube="));
      $pos=strpos($temp,'='); $pos1=strpos($temp, '<br>');
      $parent=substr($temp, $pos+1, $pos1-$pos-1);

      //having the parent tube add it to the samples list if its not there
      if(array_key_exists($parent, $samples)){
         $parentSample=$samples[$parent];
         $parentSample['aliquots'][]=$t['SampleID'];  //since the parent is there, just add the aliquot to the list of aliquots
         $samples[$parent]=$parentSample;
      }
      else{ //the parent aint there, so add it and all the aliquots
         //clinical observations
         $temp=substr($t['Comments'],strpos($t['Comments'],"Clinical History="));
         $pos=strpos($temp,'='); $pos1=strpos($temp, '<br>');
         $clinical=substr($temp, $pos+1, $pos1-$pos-1);
         //comments
         $temp=substr($t['Comments'],strpos($t['Comments'],"Comments="));
         $pos=strpos($temp,'='); $pos1=strpos($temp, '<br>');
         $comments=substr($temp, $pos+1, $pos1-$pos-1);
         //create the parent sample and add it to the animal samples
         $parentSample=array();
         $parentSample['aliquots']=array($t['SampleID']);
         $parentSample['clinical']=$clinical;
         $parentSample['comments']=$comments;
         $parentSample['timestamp']=$t['VisitDate'];
         $parentSample['longitude']=$t['Longitude'];
         $parentSample['latitude']=$t['Latitude'];
         $parentSample['collector']=$t['name'];
         $parentSample['animal']=$t['AnimalID'];
         $samples[$parent]=$parentSample;
      }
   }
   //we have all the data, so call the function to create the interface
   Search();
   if(count($samples)!=0) AnimalResults($general, $samples, $animalId, $searchBySamples);
}

/**
 * Creates the search interface for any data found.
 *
 * @param <array> $general An array with the general information about the animal
 * @param <array> $samples An array with all the samples info which belong to this animal
 */
function AnimalResults($general, $samples, $searchItem, $searchBySamples) {
   if(!$searchBySamples) $addinfo="The following samples and aliquotes were collected from $searchItem.";
   else $addinfo="Error! The sample $searchItem is erronous. In the database it is indicated that it came from different animals!!";

   $content=<<<CONTENT
      <div id='search_results'>
         $addinfo
      <div id='animal_data' style='border: none;'>
         <table>
            <tr><td>Animal Id: $searchItem</td><td>Farmer: Farmer Iks</td></tr>
            <tr><td>Animal Age: About an year</td><td>Farm Location: Sangailu</td></tr>
            <tr><td>&nbsp;</td><td>Farm Coordinates: 0157.7110S  04012.2541E</td></tr>
         </table>
      </div>
      <div id='samples' style='border: none;'>
         <table cellspacing=2>
CONTENT;
   //using the samples information that is received, create the samples interface
   $i=1;
   foreach($samples as $key => $t) {
      if($i==0) $content.="<tr>";
      $content.="<td><b><a href='javascript:;' onClick='displayExtraInfo(\"".$t['timestamp']."\",\"".$t['latitude']."\",\"".$t['longitude']."\",\"".$t['collector']
         ."\",\"".$t['comments']."\",\"".$t['clinical']."\",\"".$t['animal']."\");'>$key (".$t['animal'].")</a></b>";
      $content.="<ul>";
      foreach($t['aliquots'] as $x) $content.="<li><a href='$pageref?page=searching_sorting&searchItem=$x'>$x</a></li>";
      $content.="</ul></td>";
      if(($i%6)==0) $content.="</tr>\n";
      $i++;
   }
   if(!($i%6)==0) {   //hatukumaliza vizuri, so close the row
      $content.=str_repeat("<td width='130px'>&nbsp;</td>",(6-($i%6)+1))."</tr>";//"<td colspan='".(6-($i%6)+1)."'>&nbsp;</td></tr>";
   }
   $content.=<<<CONTENT
       </table>
      </div>
      <div id='other_data'>
      <table>
         <tr><td>Time Stamp:</td><td id='timestamp'>&nbsp;</td></tr>
         <tr><td>Animal:</td><td id='animal'>&nbsp;</td></tr>
         <tr><td>Longitude:</td><td id='long'>&nbsp;</td></tr>
         <tr><td>Latitude:</td><td id='lat'>&nbsp;</td></tr>
         <tr><td>Collector:</td><td id='collector'>&nbsp;</td></tr>
         <tr><td>Comments:</td><td id='comments'>&nbsp;</td></tr>
         <tr><td>Clinical:</td><td id='clinical'>&nbsp;</td></tr>
      </table>
      </div>
   </div>
CONTENT;
   echo $content;
}

/**
 * Creates the searching and sorting home page. to be used in aliquots sorting
 */
function SearchSortHome($addinfo='') {
   if($addinfo=="") $addinfo="Enter or scan the aliquot you want to sort.";
   $content=<<<CONTENT
   <div id="search_form">
      <form name="form" action="$pageref?page=searching_sorting" method="POST">
         <span id="search_mssg">$addinfo</span><br />
         <input type="text" name="searchItem" size="15px" id="searchItemId" value="" />   <input type="submit" name="find" id="submitId" value="FIND" />
      </form>
   </div>
CONTENT;
   echo $content;
}

/**
 * creates the output interface after sorting the aliquots. determines automatically where an aliquot ought to be placed and its corresponding location
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
function SearchSortResults($searchId, $addinfo='') {
global $config, $query, $contact, $nextBox, $nextPosition;
   $res=Connect2DB($config);
   if(is_string($res)) {
      SearchSortHome("There was an error while connecting to the database.$contact");
      return;
   }
   $query="select * from aliquots where SourceID=(select SourceID from aliquots where AliquotID = '$searchId') order by AliquotNo";
   $results=GetQueryValues($query, MYSQL_ASSOC);
   //LogError();
   if(is_string($results)){
      SearchSortHome("There was an error while fetching data from the database.$contact");
      return;
   }

echo SearchSortHome($addinfo);
$content=<<<CONTENT
   <div id='search_sort_results'>
      <div id='animal_data'>
         <table>
CONTENT;
echo $content;
if($results[0]['AnimalID']==NULL) $results[0]['AnimalID']='Error';
echo "<tr><td colspan='2'>Animal Id: <b>".$results[0]['AnimalID']."</b></td><td colspan='2'>Source Sample: <b>".$results[0]['SourceID']."</b></td></tr>";
$content=<<<CONTENT
         </table>
      </div>
      <div id='aliquots'>
         <form name="form" action="$pageref?page=update_positions" method="POST">
         <table cellspacing='0'>
            <tr><th class='left'>Destination</th><th>Aliquot</th><th>TrayId</th><th>Position in Tray</th></tr>
CONTENT;
echo $content;

$submit=false; //do we create the submit button or not
$box="<input type='text' name='box' size='15px' value='$nextBox' />"; $position="<input type='text' name='position' size='15px' value='$nextPosition' />";

//KEMRI aliquots
if(isset($results[3]['box']) && isset($results[3]['position'])){ //the aliquot has its place already
   if(strcasecmp($results[3]['AliquotID'],$searchId)==0) $class='kemri_aliquots';
   else $class='';
   $box=$results[3]['box']; $position=$results[3]['position'];
}
elseif(!isset($results[3]['box']) && !isset($results[3]['position']) && strcasecmp($results[3]['AliquotID'],$searchId)==0){ //we should assign a place to the aliquot
   $class='kemri_aliquots';  $submit=true;
   $res=NextSlot($searchId, 3);
   if($res) return;
   $box="<input type='text' name='box' size='15px' value='$nextBox' />"; $position="<input type='text' name='position' size='15px' value='$nextPosition' />";
}
else{ $class=''; $box='&nbsp;'; $position='&nbsp;'; }
if(isset($results[3])){
   echo "<tr class='$class'><td class='left'>KEMRI:</td><td>".$results[3]['AliquotID']." (".$results[3]['AliquotNo'].")</td><td>$box</td><td>$position</td></tr>";
}

//ILRI aliquots
if(isset($results[1]['box']) && isset($results[1]['position'])){ //the aliquot has its place already
   if(strcasecmp($results[1]['AliquotID'],$searchId)==0) $class='ilri_aliquots';
   else $class='';
   $box=$results[1]['box']; $position=$results[1]['position'];
}
elseif(!isset($results[1]['box']) && !isset($results[1]['position']) && strcasecmp($results[1]['AliquotID'],$searchId)==0){ //we should assign a place to the aliquot
   $class='ilri_aliquots';  $submit=true;
   $res=NextSlot($searchId, 2);
   if($res) return;
   $box="<input type='text' name='box' size='15px' value='$nextBox' />"; $position="<input type='text' name='position' size='15px' value='$nextPosition' />";
}
else{ $class=''; $box='&nbsp;'; $position='&nbsp;'; }
echo "<tr class='$class'><td class='left'>ILRI:</td><td>".$results[1]['AliquotID']." (".$results[1]['AliquotNo'].")</td><td>$box</td><td>$position</td></tr>";

//ICIPE aliquots
if(isset($results[0]['box']) && isset($results[0]['position'])){ //the aliquot has its place already
   if(strcasecmp($results[0]['AliquotID'],$searchId)==0) $class='icipe_aliquots';
   else $class='';
   $box=$results[0]['box']; $position=$results[0]['position'];
}
elseif(!isset($results[0]['box']) && !isset($results[0]['position']) && strcasecmp($results[0]['AliquotID'],$searchId)==0){ //we should assign a place to the aliquot
   $class='icipe_aliquots'; $submit=true;
   $res=NextSlot($searchId, 1);
   if($res) return;
   $box="<input type='text' name='box' size='15px' value='$nextBox' />"; $position="<input type='text' name='position' size='15px' value='$nextPosition' />";
}
else{ $class=''; $box='&nbsp;'; $position='&nbsp;'; }
echo "<tr class='$class'><td class='left'>ICIPE:</td><td>".$results[0]['AliquotID']." (".$results[0]['AliquotNo'].")</td><td>$box</td><td>$position</td></tr>";

//KARI aliquots
if(isset($results[2]['box']) && isset($results[2]['position'])){ //the aliquot has its place already
   if(strcasecmp($results[2]['AliquotID'],$searchId)==0) $class='kari_aliquots';
   else $class='';
   $box=$results[2]['box']; $position=$results[2]['position'];
}
elseif(isset($results[2]['box'])==NULL && isset($results[2]['position'])==NULL && strcasecmp($results[2]['AliquotID'],$searchId)==0){ //we should assign a place to the aliquot
   $class='kari_aliquots'; $submit=true;
   $res=NextSlot($searchId, 4);
   if($res) return;
   $box="<input type='text' name='box' size='15px' value='$nextBox' />"; $position="<input type='text' name='position' size='15px' value='$nextPosition' />";
}
else{ $class=''; $box='&nbsp;'; $position='&nbsp;'; }


if($submit){
   echo "<tr class='$class'><td>KARI:</td><td>".$results[2]['AliquotID']." (".$results[2]['AliquotNo'].")</td><td>$box</td><td>$position</td></tr>";
   echo '<tr><td colspan="4" align="center" class="left bottom"><input type="submit" name="find" id="submitId" value="Update" /></td></tr>';
   echo "<input type='hidden' name='aliquot' value='$searchId' />";
}
else{
   echo "<tr class='$class'><td class='left bottom'>KEMRI:</td><td class='bottom'>".$results[2]['AliquotID']." (".$results[2]['AliquotNo'].")</td><td class='bottom'>$box</td><td class='bottom'>$position</td></tr>";
}
$content=<<<CONTENT
         </table>
       </form>
      </div>
   </div>
CONTENT;
echo $content;
}

/**
 * The home page
 */
function Home($addinfo='') {
   $content=<<<CONTENT
<div>
   <div id='addinfo'>$addinfo</div>
   <ol class='ol_li'>
      <li><a href="$pageref?page=sort_aliquots">Sort Aliquots</a></li>
      <li><a href="$pageref?page=merge">Merge different collections</a></li>
      <li><a href="$pageref?page=backup">Backup</a></li>
   </ol>
</div>
CONTENT;
   echo $content;
}

/**
 * Given an aliquot a box name and a position it places the aliquot in the passed position
 *
 * @param string $aliquot   The aliquot id we want to place
 * @param string $box       The tray name where the aliquot is to be stored
 * @param integer $position The position in the box to store the aliquot
 * @return none    Returns nothing.
 */
function UpdateAliquotsPosition($aliquot, $box, $position){
global $config, $query, $contact, $tables;
   $res=Connect2DB($config);
   if(is_string($res)) {
      SampleProcessing("<div class='error'>There was an error while connecting to the database.$contact</div>");
      return;
   }
   //we have already done the data integrity checks, so its a matter of updating the position
   //check whether the aliquot exists
   $query="select * from {$config['temp_dbase']}.aliquots where label='$aliquot'";
   $tAliquot=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($tAliquot)){
      SampleProcessing("<div class='error'>There was an error while fetching data from the database.$contact</div>");
      return;
   }
   elseif(count($tAliquot)==0){
      SampleProcessing("<div class='error'>The aliquot '$aliquot' does not exist hence cannot be updated.</div>");
      return;
   }
   //check whether there is another aliquot stored in this position
   $query="select * from {$config['temp_dbase']}.aliquots where tray='$box' and position=$position";
   $alloc=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($alloc)){
      SampleProcessing("<div class='error'>There was an error while fetching data from the database.$contact</div>");
      return;
   }
   elseif(count($alloc)!=0){
      $mssg="<div class='error'>Another aliquot(".$alloc[0]['label'].") is stored in the position that you have defined.<br />Please select another position.</div>";
      SampleProcessing($mssg);
      return;
   }
   //store the aliquot
   StartTrans();
   $cols=array('tray', 'position'); $colvals=array($box, $position);
   $results=UpdateTable("{$config['temp_dbase']}.aliquots", $cols, $colvals, 'label', $aliquot);
   if(is_string($results)){
      RollBackTrans();
      SampleProcessing("<div class='error'>There was an error while updating the database.$contact</div>");
      return;
   }
   else{
      CommitTrans();
      SampleProcessing("<div class='no_error'>The sample position has been successfully updated.</div>");
      return;
   }
}

/**
 * Calculates the next empty slot in the next box. This is the position where the current aliquot will be placed
 *
 * @global <string> $nextBox
 * @global <string> $nextPosition
 * @param <string> $searchId
 * @param <integer> $type
 * @return <type> Returns 0 on success, else returns 1
 */
function NextSlot($searchId, $type){
global $config, $query, $contact, $tables, $nextBox, $nextPosition;
   if($type==1) $boxType='TAVQC';
   elseif($type==2) $boxType='TAVQL';
   elseif($type==3) $boxType='TAVQM';
   elseif($type==4) $boxType='TAVQA';
   //$boxType='TAVQ'.$type;
   $query="select box, position from aliquots where box is not null and position is not null and box like '$boxType%' order by box desc, position desc limit 0,1";
   $results=GetQueryValues($query, MYSQL_ASSOC);
   if(is_string($results)){
      SearchSortResults($searchId, "There was an error while fetching data from the database.$contact");
      return 1;
   }
   if($results[0]['box']==NULL || $results[0]['position']==NULL){
      if($type==1) $nextBox='TAVQC00001';
      elseif($type==2) $nextBox='TAVQL00001';
      elseif($type==3) $nextBox='TAVQM00001';
      elseif($type==4) $nextBox='TAVQA00001';
      $nextPosition=1;
   }
   else{
      if($results[0]['position']==100){
         $nextInt=(substr($results[0]['box'], -5)+1);
         $nextBox=$boxType.str_repeat('0', 5-strlen($nextInt)).$nextInt; $nextPosition=1;
      }
      else{
         $nextBox=$results[0]['box']; $nextPosition=$results[0]['position']+1;
      }
   }
return 0;
}
//=============================================================================================================================================

function UploadInterface($addinfo=''){
global $data, $pageref;
if(isset($addinfo) && $addinfo!='') $addinfo="<div id='addinfo'>$addinfo</div>";
?>
   <div id='page_header'>Select files to upload</div>
   <?php echo $addinfo; ?>
   <form enctype="multipart/form-data" name="upload" action="<?php echo $pageref; ?>?page=save_file" method="POST">
      <input type="hidden" value="10240000" name="MAX_FILE_SIZE"/>
      <div id='uploads'>
         Sample's File: <input type="file" name="samples_batch[]" value="" width="50"/>
      </div>
      <div id='links'>
         <input type="submit" value="Upload" name="upload" /><input type="reset" value="Cancel" name="cancel" />
      </div>
   </form>
<?php
}
//=============================================================================================================================================

function SaveUploads(){
global $uploadedFiles, $data;
   $err_occ=0;
   //print_r($_FILES);
   if(is_dir($uploadedFiles['location'])){        //the dir exists//check if its writable; if not make it writable
 		if(!is_writable($uploadedFiles['location'])) {chmod($uploadedFiles['location'],0766); /*echo 'made it writable';*/}
   }
   else{
   	if(!mkdir($uploadedFiles['location'],0766)){
   		$err_occ=1;
         LogError('Error while creating the destination folder for uploaded files.');
   		die("Cannot create the destination folder.</div>");
   	}
   }

   //save the samples file
   $err_msg='';
   for($i=0;$i<count($_FILES['samples_batch']['name']);$i++){
      $err_code=$_FILES['samples_batch']['error'][$i];
      //LogError("Error Code: $err_code".count($_FILES['samples_batch']));
      if($err_code==4) continue;        //no file selected
      //check for the errors that might have occurred
      if($err_code!=0 && $err_code!=4){
      	if($err_code<3){
            $err_msg.=$_FILES['samples_batch']['name'][$i].' Max file size allowed was exceeded.<br>';
            LogError('The max file size was exceeded while trying to upload '.$_FILES['samples_batch']['name'][$i]);
         }
         if($err_code==3){
            $err_msg.=$_FILES['samples_batch']['name'][$i].' The file was partially uploaded.<br>';
            LogError('The '.$_FILES['samples_batch']['name'][$i].' was partially uploaded and discarded.');
         }
         $err_occ=1; continue;
      }

      //only allow xml files to be uploaded
      //LogError($_FILES['samples_batch']['type'][$i]);
      if($_FILES['samples_batch']['type'][$i]!='text/xml'){
      	$err_msg.=$_FILES['samples_batch']['name'][$i].' Not an allowed file type.<br>';
         LogError('Attempt to upload a wrong file: '.$_FILES['samples_batch']['name'][$i].' is not an allowed file type.'.$_FILES['samples_batch']['type'][$i]);
       	$err_occ=1; continue;
      }

      //Dont allow importation of files larger than 10Mb
      if($_FILES['samples_batch']['size'][$i] > $uploadedFiles['max_size']){
      	$err_msg.=$_FILES['samples_batch']['name'][$i].' The uploaded file is bigger than 10Mb. You are only allowed to import files less than 10Mb.<br>';
         LogError('The uploaded file '.$_FILES['samples_batch']['name'][$i].' exceeds the limit of 10Mb.');
         $err_occ=1;  continue;
      }

      //check the correctness of the file
      $res=array();
      if(!eregi('_samples.xml$', $_FILES['samples_batch']['name'][$i], $res) && !eregi('_samples_two.xml$', $_FILES['samples_batch']['name'][$i], $res)){
         $err_msg.=$_FILES['samples_batch']['name'][$i].' The uploaded file doesnt have the right name format.<br>';
         LogError('The uploaded file '.$_FILES['samples_batch']['name'][$i].' has a wrong name.');
         $err_occ=1;  continue;
      }
      //create the destination folder name
      $destfolder=$uploadedFiles['location'].basename($_FILES['samples_batch']['name'][$i]);
      //move the uploaded file to the final destination
      if(!move_uploaded_file($_FILES['samples_batch']['tmp_name'][$i],$destfolder)){
         $err_msg.=$_FILES['samples_batch']['name'][$i].'. There was an error while uploading the files.';
         LogError('There was an error while uploading the files.');
         $err_occ=1;  continue;
      }
   }
   if($err_occ==1){ UploadInterface($err_msg); return;}
   else{
      //now process the uploaded file
      $addinfo=ProcessUploadedFile();
      //$addinfo='The files have been successfully uploaded.';
      Home($addinfo);
   }
}
//=============================================================================================================================================

/**
 * Processes the uploaded file and saves the info to a dbase.
 * 
 * @global <type> $config
 * @global <type> $uploadedFiles
 * @global <type> $contact
 * @return <type> 
 */
function ProcessUploadedFile(){
global $config, $uploadedFiles, $contact, $query;
   $res=Connect2DB($config);
   if(is_string($res)) {
      return "There was an error while connecting to the database.$contact";
   }
   //get all the files in the uploaded files location and process them
   $files=scandir($uploadedFiles['location']);
   $res=array(); $res1=array();
   StartTrans();
   foreach($files as $t){
      if($t=='.' || $t=='..') continue;   //the empty folders
      if(!eregi('_samples.xml$', $t, $res) && !eregi('_samples_two.xml$', $t, $res)) continue;  //not a kind of file thar we want
      else{
         $curLocation=substr($t, 0, strpos($t,'_samples'));
      }
      $fd=fopen($uploadedFiles['location'].$t, 'rt');
      if(!$fd) return "There was an error while opening the file $t";
      //$regexp='/[a-z]{3}[0-9]{6}\([0-9]{2}\/[0-9]{2}\/[0-9]{2,4}\s[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3},\s(([0-9]{2}\.[0-9]{4}[NS][\s]{1,2}[0-9]{3}\.[0-9]{4}[EW])|(No\sData))\)/i';
        $regexp='/(([a-z]{3})|([a-z][0-9][a-z]))[0-9]{6}\([0-9]{2}\/[0-9]{2}\/[0-9]{2,4}\s[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}/i';//,\s(([0-9]{2}\.[0-9]{4}[NS][\s]{1,2}[0-9]{3}\.[0-9]{4}[EW])|(No\sData))\)/i';
      while($text=fgets($fd)){
         if(preg_match('/^<?xml/i', $text, $res) || preg_match('/\?>$/i', $text, $res)) continue;
         if($text=='') continue;
         $text=trim($text);
         if($start_reading){
            $start_reading=false;
            //echo $data.'<br />';
            $data=trim($data);
            if(preg_match('/<\/Comments>/i', $text, $res)) $curAnimal['comments']=$data;
            elseif(preg_match('/<\/Clinical_History>/i', $text, $res)) $curAnimal['clinical'] = $data;
            elseif(preg_match('/<\/Samples>/i', $text, $res)){
               //echo $data.'<br />';
               preg_match_all($regexp, $data, $res1);
               if(count($res1[0])==0) echo $data.'<br />';
               //print_r($res1);
               $curAnimal['samples']=array();
               foreach($res1[0] as $s){
                  //print_r($s); echo count($res[0])."<br />";
                  $sample=array();
                  $sample['label']=strtoupper(substr($s, 0, strpos($s,'(')));
                  preg_match('/[0-9]{2}\/[0-9]{2}\/[0-9]{2,4}\s[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}/', $s, $res); $sample['date']=$res[0];
                  if(preg_match('/No\sData/i', $s, $res)){
                     $sample['lat']=''; $sample['longitude']='';// echo 'no data';
                  }
                  else{
                     $sample['longitude']=$curAnimal['longitude']; $sample['lat']=$curAnimal['lat'];
                     //preg_match('/(([0-9]{2}\.[0-9]{4}[NS])|(No\sData))/i', $s, $res); $sample['lat']=$res[0];
                     //preg_match('/(([0-9]{3}\.[0-9]{4}[EW])|(No\sData))/i', $s, $res); $sample['longitude']=$res[0];
                  }
                  $curAnimal['samples'][]=$sample;
               }
            }
            else{
               $data.=trim($text);
               $start_reading=true;
            }
            continue;
         }
         if(preg_match('/<Samples>/i', $text, $res) || preg_match('/<Comments>/i', $text, $res) || preg_match('/<Clinical_History>/i', $text, $res)){
            $data=''; $start_reading=true; continue;
         }
         $data=substr($text, strpos($text, '>')+1, strrpos($text, '<') - strpos($text, '>')-1);
         //echo $data;
         if(preg_match('/^<AnimalData>/i', $text, $res)){
            $curAnimal=array(); //start with a new animal
            continue;
         }
         elseif(preg_match('/^<\/AnimalData>/i', $text, $res)){   //try and save the animal details to the database
            //print_r($curAnimal); echo '<br />';
            //check if the animal is already saved before
            $animalId=GetSingleRowValue("{$config['temp_dbase']}.export_animals", 'id', 'animal_id', strtoupper($curAnimal['id']));
            if(is_numeric($animalId)){/*The animal has already been saved. no need to further save it.*/}
            elseif(is_string($animalId)) {
               RollBackTrans(); return 'There was an error while fetching data from the database.'.$animalId;
            }
            elseif(!is_numeric($animalId)) {
               $cols=array('animal_id', 'location');
               $colvals=array(strtoupper($curAnimal['id']), $curLocation);
//               LogError(print_r($curAnimal, true));
               $results=InsertValues("{$config['temp_dbase']}.export_animals", $cols, $colvals);
               //LogError();
               if(is_string($results)) {
                  RollBackTrans(); return 'There was an error while adding data to the database';
               }
               else $animalId=mysql_insert_id();
            }

            $cols=array('label', 'animal_id', 'longitude', 'latitude', 'timestamp', 'collector', 'comments', 'clinical_observation', 'visit_date');
            foreach($curAnimal['samples'] as $s){
               $colvals=array(strtoupper($s['label']), $animalId, $s['longitude'], $s['lat'], $s['date'], $curAnimal['collector'],
                   mysql_real_escape_string($curAnimal['comments']), $curAnimal['clinical'], $curAnimal['date']);
               //check if the sample is saved, if it is, try and update the data
               $sampleId=GetSingleRowValue("{$config['temp_dbase']}.export_samples", 'id', 'label', $s['label']);
//               echo "$query<br />";
               if($sampleId==-2){
                  RollBackTrans(); return "There was an error while fetching data from the database.$contact";
               }
               elseif(is_null($sampleId)) {
                  var_dump($sampleId);
                  $results=InsertValues("{$config['temp_dbase']}.export_samples", $cols, $colvals);
                  if(is_string($results)) {
                     RollBackTrans(); return 'There was an error while adding data to the database';
                  }
               }
               elseif(is_numeric($sampleId)) {
                  $results=UpdateTable("{$config['temp_dbase']}.export_samples", $cols, $colvals, 'id', $sampleId);
                  if(is_string($results)) {
                     RollBackTrans(); return 'There was an error while adding data to the database';
                  }
               }
               //LogError();
            }
         }
         elseif(preg_match('/<\/AnimalID>$/i', $text, $res) && preg_match('/^<AnimalID>/i', $text, $res)){
            $temp=preg_match('/[a-z]{3}[0-9]{3,4}/i', $text, $res);
            $curAnimal['id']=strtoupper($res[0]);
         }
         elseif(preg_match('/visitdate>/i', $text, $res)) $curAnimal['date']=$data;
         elseif(preg_match('/gps_coords>/i', $text, $res)){
            if($data!='No Data'){
               $res=preg_split('/[\s]+/', $data); $curAnimal['lat']=$res[0]; $curAnimal['longitude']=$res[1];
            }
            else{
               $curAnimal['lat']=''; $curAnimal['longitude']='';
            }
         }
         elseif(preg_match('/Sample_Collector>/i', $text, $res)) $curAnimal['collector']=$data;
         $text='';
      }
      fclose($fd);
      //delete the file that we have processed
      if(!unlink($uploadedFiles['location'].$t)){
         RollBackTrans();
         return "There was an error while deleting the processed file. The changes have not been saved.<br />$contact";
      }
   }
   CommitTrans();
   //echo GetSingleRowValue('{$config['temp_dbase']}.export_samples', 'count(id)', 'id', 'is not null');
   //RollBackTrans();
   return 'The uploaded file has been successfully processed.';
}
//=============================================================================================================================================

/**
 * The main search and sort function. searches for the searched item and if it is an aliquot determines its position and tray. if its a parent
 * sample it sav$noOfAliquotses it. It also receives previous values that are automatically saved
 *
 * @param  string $add_message       An optional string with a message to be displayed
 * @global type $query               The global query value
 * @global array $config             Configuraton settings for connecting to the database
 * @global string $nextBox           The tray label to save the aliquot to
 * @global integer $nextPosition     The position in the next tray to save to
 * @global array $aliquot_settings   The aliquot settings to use
 * @return <type>
 */
function SampleProcessing($add_message = ''){
//this is the big one
global $query, $config, $contact, $nextBox, $nextPosition, $aliquot_settings;
   //print_r($_POST);
   $searchItem=$_POST['searchItem']; $parent_sample=$_POST['parent']; $animal=$_POST['curAnimal'];
   $save_tray=$_POST['nextTray']; $save_position=$_POST['nextPosition']; $prev_sample=$_POST['prevSample'];
   $aliquot_number=$_POST['aliquot_number'];
   $addinfo='';
   $errorClass = 'error';
   echo '<form name="form" action="?page=sort_aliquots" method="POST" id="searchFormId">';
   //check the aliquoting settings
   $res=AliquotingSettings();
//   print_r($aliquot_settings, true);
   $noOfAliquots = count($aliquot_settings['trays']);
   if($res==1){
      echo "</form>";   //close the form and return
      return;
   }
   //connect to the db
   $res=Connect2DB($config);
   if(is_string($res)) {
      $addinfo="There was an error while connecting to the database.$contact";
   }
//   LogError(print_r($aliquot_settings, true));
   //check whether there is some saving that should take place before looking for other things
   if(preg_match('/^'.$aliquot_settings['aliquot_format2use'].'$/i', $prev_sample, $res) && isset($save_tray) && $save_tray!='' && isset($save_position) &&
      $save_position!='' && isset($aliquot_number) && $addinfo=='') {
      //we have something to save, so save it, bt first confirm that the aliquot is right
//      echo '<pre>'.print_r($aliquot_settings, true).'</pre>';
//      echo "{$aliquot_settings['trays_format2use'][$aliquot_number-1]} -- $save_tray<br />";
      if(preg_match('/^'.$aliquot_settings['trays_format2use'][$aliquot_number-1].'$/i', $save_tray, $res) &&
         ($save_position>0 && $save_position <= $aliquot_settings['trays'][$aliquot_number-1]['size']) ) {
            $parentSampleId=GetSingleRowValue("{$config['temp_dbase']}.export_samples", 'id', array('label'), array($parent_sample));
            if($parentSampleId==-2) {
               $addinfo="There was an error while fetching data from the database.$contact";
            }
            else {
               $cols=array('label', 'parent_sample', 'aliquot_number', 'tray', 'position');
               $colvals=array(strtoupper($prev_sample), $parentSampleId, $aliquot_number, $save_tray, $save_position);
               $results=InsertValues("{$config['temp_dbase']}.aliquots", $cols, $colvals);
               if(is_string($results)) $addinfo="There was an error while saving the aliquot $prev_sample.";
            //echo "save this $save_aliquot $save_position $save_tray";
            }
      }
      else{
         $addinfo="Unable to save $prev_sample($aliquot_number) in tray $save_tray at position $save_position.<br />$contact";
         $save_position=''; $save_tray='';
      }
   }
   if($addinfo!=''){}   //we have an error so stop execution
   elseif(isset($searchItem) && $searchItem!='' && $searchItem!='undefined') {
      //we have a sample, check if it is an aliquot or a real sample
      if(preg_match("/^".$aliquot_settings['aliquot_format2use']."$/i", $searchItem)) {
         //check if this aliquot has been saved before         
//         $query="select a.parent_sample, b.label, c.animal_id from {$config['temp_dbase']}.aliquots as a inner join {$config['temp_dbase']}.export_samples as b on a.parent_sample=b.id "
//         ."inner join {$config['temp_dbase']}.export_animals as c on b.animal_id=c.id where a.label='$searchItem'";

         $query="select a.parent_sample, b.label, c.animal_id from {$config['temp_dbase']}.aliquots as a inner join {$config['temp_dbase']}.export_samples as b on a.parent_sample=b.id "
         ."inner join {$config['temp_dbase']}.export_animals as c on b.animal_id=c.id where a.label='$searchItem'";
         $results=GetQueryValues($query, MYSQL_ASSOC);
         if(count($results)>0) {
            $query="select * from {$config['temp_dbase']}.aliquots where parent_sample=".$results[0]['parent_sample']." order by aliquot_number";
            $aliq=GetQueryValues($query, MYSQL_ASSOC);
            if(is_string($aliq)) {
               $addinfo="There was an error while fetching data from the database.<br />$contact";
            }
            else{
               $animal=$results[0]['animal_id']; $parent_sample=$results[0]['label'];
               $addinfo="The aliquot $searchItem has already been saved.";
            }
            $save_position=''; $save_tray='';
         }
         elseif(isset($parent_sample) && $parent_sample!='undefined') {
            if($addinfo=='') { //we dont have an error
               //get the metadata for this sample, ie from which animal its coming from and the animal metadata
               $query="select a.id, a.label, b.animal_id, b.location from {$config['temp_dbase']}.export_samples as a inner join {$config['temp_dbase']}.export_animals as b on a.animal_id=b.id "
                  ."where lower(a.label) like lower('$parent_sample')";
               $results=GetQueryValues($query, MYSQL_ASSOC);
               if(is_string($results)){   //there is an error while fetching data from the dbase
                  $addinfo="There was an error while fetching data from the database.<br />$contact";
               }
               elseif(count($results)>1){ //we have more than one sample in the dbase. THIS IS A HUGE ERROR AS WE ARE ONLY DEALING WITH UNIQUE SAMPLES
                  $addinfo="There is an error in the database. There can be only one sample with this id.<br />$contact";
               }
               elseif(count($results)==0){   //the sample is not in the dbase
                  $addinfo='The sample is not in the database';
               }
               elseif(count($results)==1){   //we have a hit, the sample is in the dbase
                  //check if there are other aliquots from this sample
                  $query="select * from {$config['temp_dbase']}.aliquots where parent_sample=".$results[0]['id']." order by aliquot_number";
                  $aliq=GetQueryValues($query, MYSQL_ASSOC);
                  if(is_string($aliq)){   //error while fetching data from the dbase
                     $addinfo="There was an error while fetching data from the database.<br />$contact";
                  }
                  elseif(count($aliq)>$aliquot_settings['aliquot_number']-1){
                     //if there are enough aliquots it means we dont need to add another aliquot
                     $addinfo="Error! A sample can only have ".$aliquot_settings['aliquot_number']." aliquots.";
                  }
                  else{
                     $animal=$results[0]['animal_id'];
                     $curAliquotNo = null;
                     //determine which aliquot number we really want. will take care if we delete an aliquot of a institution defined first
                     //expects to find a matching set of aliquot_number and the index. In case this match is inconsistent,
                     //create an generate a position to correct this
                     $generated = false;
                     for($i=0; $i<$noOfAliquots; $i++){
                        if($i+1 != $aliq[$i]['aliquot_number']){
                           $generated = true;
                           NewNextSlot($searchItem, $i+1);
                           $curAliquotNo = $i+1;
                           break;
                        }
                     }
                     if($generated==false) NewNextSlot($searchItem, count($aliq)+1);
                     //now create the place holder for this new sample
                     $tray="<input type='text' name='nextTray' size='15px' id='nextTrayId' value='$nextBox' />";
                     $position="<input type='text' name='nextPosition' size='15px' id='nextPositionId' value='$nextPosition' />"
                        ."<input type='hidden' size='15px' name='saveAliquot' id='saveAliquotId' value='$searchItem' />"
                        ."<input type='hidden' size='15px' name='aliquot_number' id='aliquotNumberId' value='$curAliquotNo' />";
                     $aliq[count($aliq)]=array('label'=>$searchItem, 'tray'=>$tray, 'position'=>$position);
                     $save_tray=$nextBox; $save_position=$nextPosition;
                  }
               }
            }
         }
         else {
            $addinfo='We have an aliquot but no parent sample';
         }
      }
      elseif(preg_match('/^'.$aliquot_settings['parent_format2use'].'$/i', $searchItem)) {
         //try and see that it is in the db before making it the parent sample
         $res=Connect2DB($config);
         if(is_string($res)) {
            $addinfo="There was an error while connecting to the database.$contact";
         }
         $query="select a.id, a.label, b.animal_id, b.location, a.comments, b.organism from {$config['temp_dbase']}.export_samples as a inner join {$config['temp_dbase']}.export_animals as b on a.animal_id=b.id "
            ."where lower(a.label) = lower('$searchItem')";
         $results=GetQueryValues($query, MYSQL_ASSOC);
         //LogError();
         if(is_string($results)) {
             $addinfo="There was an error while fetching data from the database.<br />$contact";
         }
         elseif(count($results)==0){
            $parentSampleId=''; $animal='';
            $addinfo="The sample $searchItem was not found in the database.";
         }
         elseif(count($results)!=1) {
            $addinfo="There is an error in the database. There can be only one sample with this id.<br />$contact";
         }
         else {
            //check if we have some aliquots from this sample
            $comments=mysql_real_escape_string($results[0]['comments']); $organism=$results[0]['organism'];
            $query="select * from {$config['temp_dbase']}.aliquots where parent_sample=".$results[0]['id']." order by aliquot_number";
            $aliq=GetQueryValues($query, MYSQL_ASSOC);
            if(is_string($aliq)) {
               $addinfo="There was an error while fetching data from the database.<br />$contact"; $aliq=array();
            }
            elseif(count($aliq)==0) { //if there are 3 aliquots it means we dont need to add another aliquot
               $aliq=array();
            }
            $parent_sample=strtoupper($searchItem); $animal=$results[0]['animal_id'];
         }
      }
      else{
         $addinfo='Unrecognized Sample. Try again';
      }
   }
   else{
      $addinfo='Please enter the sample label to search for.';
      $errorClass = 'no_error';
   }
   if($addinfo=='' || !isset($addinfo)){
      $addinfo = 'Enter or scan the aliquot you want to sort.';
      $errorClass = 'no_error';
   }

$content.=<<<CONTENT
<div id="search_form">
      <span id="search_mssg">$add_message<div class='$errorClass'>$addinfo <br />(Searched Sample: $searchItem)</div></span><br />
      <input type="text" name="searchItem" size="15px" id="searchItemId" value="" onkeyup="Samples.simulateEnterButton(event);" />
      <input type="button" name="find" id="submitId" value="FIND" onClick="Samples.search();" />
      <input type="hidden" value="$searchItem" name="prevSample" id="prevSampleId" />
      <input type="hidden" value="$parent_sample" name="parent" id="parentSampleId" />
      <input type="hidden" value="$animal" name="curAnimal" id="animalId" />
      <input type="hidden" value="$save_tray" name="nextTray" id="parentSampleId" />
      <input type="hidden" value="$save_position" name="nextPosition" id="animalId" />

   <div id='search_sort_results'>
      <div id='animal_data'>
         <table>
            <tr><td colspan='2'>Organism: <b>$organism</b></td><td colspan='2'>Comments: <b>$comments</b></td></tr>
            <tr><td colspan='2'>Animal Id: <b>$animal</b></td><td colspan='2'>Source Sample: <b>$parent_sample</b></td></tr>
         </table>
      </div>
      <div id='aliquots'>
         <table cellspacing='0'>
            <tr><th class='left'>Destination</th><th>Aliquot</th><th>TrayId</th><th>Position in Tray</th><th>Actions</th></tr>
CONTENT;
//   LogError(print_r($aliq, true));
   //this is the point where the trays as defined and the defined aliquots converge. This system relies on one setting, ie
   //the trays as defined corresponds to the aliquot number, as they will be entered/scanned. So at this stage, since we ordered the aliquots
   //based on the aliquot number, we can say that the trays and the aliquots converge here
   $bClass=""; $addClass='';
   /**
    * var array $assignedTrays Keeps a track of the trays which have been assigned aliquots from a parent sample, such that we have a correct
    * naming of trays in case an aliquot of a tray which has a higher index is deleted
    */
   $assignedTrays = array();
   for($i=0; $i<$noOfAliquots; $i++){
      if(isset($aliq[$i]['aliquot_number'])){
         //if the aliquot is already defined, display the institution its intended to go to by getting the tray name using the aliquot number
         //and tray indexes. Add this fetched tray to the list of assignedTrays
         $predTray = $aliquot_settings['trays'][$aliq[$i]['aliquot_number']-1];
      }
      else{
         //naturally in case an aliquot is not defined, we would get the next institution to receive an aliquot. However if an aliquot of an
         //insitution with a lower index is deleted, the institution with higher indexes might already have received an aliquot. To avoid this
         //check which insitution is not on the list of assignedTrays and get the first one which is not defined.
         $t = array_diff($aliquot_settings['just_trays'], $assignedTrays);
//         echo '<pre>'.print_r($assignedTrays['just_trays'], true).'</pre>';
//         echo '<pre>'.print_r($assignedTrays, true).'</pre>';
//         echo '<pre>'.print_r($t, true).'</pre>';
//         echo key($t);
         $predTray = $aliquot_settings['trays'][key($t)];
      }
      $assignedTrays[] = $predTray['name'];
      if($i==count($aliquot_settings['trays'])-1){
         $bClass="class='bottom'"; $addClass='bottom';
      }
      if($aliq[$i]['tray']=='') $aliq[$i]['tray']='&nbsp;';
      if($aliq[$i]['position']=='') $aliq[$i]['position']='&nbsp;';
      if($aliq[$i]['label']=='') $aliq[$i]['label']='&nbsp;';
      $actions = "<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliq[$i]['label']}\", \"edit\")'>Edit</a>&nbsp;<a href='javascript:;' onClick='Samples.ukarabati(\"{$aliq[$i]['label']}\", \"delete\")'>Delete</a>";
      $content.="<tr class=''><td class='left $addClass'>".$predTray['descr'].":</td><td $bClass>{$aliq[$i]['label']}".
         "</td><td $bClass>{$aliq[$i]['tray']}</td><td $bClass>{$aliq[$i]['position']}</td><td $bClass>$actions</td></tr>";
   }
//   $content.="<tr class=''><td class='left'>ILRI:</td><td>".$aliq[0]['label']."&nbsp;</td><td>".$aliq[0]['tray']."&nbsp;</td><td>".$aliq[0]['position']."&nbsp;</td></tr>"
//     ."<tr class=''><td class='left'>ICIPE:</td><td>".$aliq[1]['label']."&nbsp;</td><td>".$aliq[1]['tray']."&nbsp;</td><td>".$aliq[1]['position']."&nbsp;</td></tr>"
//     ."<tr class=''><td class='left bottom'>DVS:</td><td class='bottom'>".$aliq[2]['label']."&nbsp</td><td class='bottom'>".$aliq[2]['tray']."&nbsp;</td><td class='bottom'>".$aliq[2]['position']."&nbsp;</td></tr>";
$content.=<<<CONTENT
         </table>
      </div>
   </div>
</div>
</form>
CONTENT;
   echo $content;
}
//=============================================================================================================================================

/**
 * Calculates the next empty slot in the next box. This is the position where the current aliquot will be placed
 *
 * @global string $nextBox
 * @global string $nextPosition
 * @param string $searchId
 * @param integer $aliquot_number
 * @return integer Returns 0 on success, else returns 1
 */
function NewNextSlot($searchId, $aliquot_number){
global $config, $query, $contact, $tables, $nextBox, $nextPosition, $aliquot_settings;
   $trayType = strtolower($aliquot_settings['trays_format2use'][$aliquot_number-1]);  //type must be from 1-4
   $pat = "and lower(b.label) rlike '^".strtolower($aliquot_settings['parent_format2use'])."$'";
   $query = "select a.tray, a.position from {$config['temp_dbase']}.aliquots as a inner join {$config['temp_dbase']}.export_samples as b on a.parent_sample=b.id "
   ."where a.tray is not null and a.position is not null and lower(a.tray) rlike '^$trayType$' $pat order by a.tray desc, a.position desc limit 0,1";
   $results = GetQueryValues($query, MYSQL_ASSOC);
   //LogError();
   if(is_string($results)){
      SearchSortResults($searchId, "There was an error while fetching data from the database.$contact");
      return 1;
   }
   preg_match('/^[a-z]+/i', $trayType, $res);
   $trayPrefix = strtoupper($res[0]);
   preg_match('/\{[0-9]+\}/i', $trayType, $res);
   preg_match('/[0-9]+/', $res[0], $res);
   $intCount = $res[0]; //echo $intCount;
   if($results[0]['tray']==NULL || $results[0]['position']==NULL){
      $nextBox=$trayPrefix.str_repeat('0', $intCount-1).'1'; $nextPosition=1;
   }
   else{
      if($results[0]['position']==$aliquot_settings['trays'][$aliquot_number-1]['size']){
         $nextInt=(substr($results[0]['tray'], -$intCount)+1);
         $nextBox=$trayPrefix.str_repeat('0', $intCount-strlen($nextInt)).$nextInt; $nextPosition=1;
      }
      else{
         $nextBox=$results[0]['tray']; $nextPosition=$results[0]['position']+1;
      }
   }
return 0;
}
//=============================================================================================================================================

/**
 * Checks and verifies the aliquot settings to be used in this session. if the settings are not set, it sets them
 *
 * @global array $aliquot_settings   An array where the aliquot settings are saved
 * @return integer    Returns 0 when all is set ok, and 1 when the seetings are not set
 */
function AliquotingSettings(){
global $aliquot_settings;
   if(isset($_POST['aliquot_settings']) && is_array($_POST['aliquot_settings'])){
      $aliq=$_POST['aliquot_settings'];
      if((!isset($aliq['parent_format']) || $aliq['parent_format']=='') ||
         (!isset($aliq['aliquot_format']) || $aliq['aliquot_format']=='') || !is_numeric($aliq['aliquot_number']) ||
         (!isset($aliq['trays']) || !is_array($aliq['trays'])) ){ //something is missing, so offer to recreate everything again
            if(!isset($aliq['parent_format']) || $aliq['parent_format']=='') $addinfo="Please enter the parent sample format.";
            if(!isset($aliq['aliquot_format']) || $aliq['aliquot_format']=='') $addinfo="Please enter the aliquout format.";
            if(!is_numeric($aliq['aliquot_number'])) $addinfo="Please enter the number of aliquots";
            if(!isset($aliq['trays']) || !is_array($aliq['trays'])) $addinfo="Please enter the tray details.";
?>
   <div id="aliquot_settings">
      <div id="addinfo"><?php echo $addinfo; ?></div>
   <table>
      <tr><td>Parent Sample Format</td><td><input type='text' name='aliquot_settings[parent_format]' size="15" /></td>
         <td>Aliquot Format</td><td><input type='text' name='aliquot_settings[aliquot_format]' size="15" /></td>
         <td>No of Aliquots</td><td><input type='text' name='aliquot_settings[aliquot_number]' size="3" id='aliquot_number_id' onBlur='Samples.generateAliquots();' /></td></tr>
      <tr id='aliquotNos'>&nbsp;</tr>
   </table>
      <div id='links'><input type='button' name='find' value='Save' onclick="Samples.search();" />
          <input type='button' name='cancel' value='Cancel' /></div>
   </div>
<?php
         return 1;   //there is an error in the defined settings. so this is the end of this thread
      }
      else{ //we have everything ok, so lets hide them, so to be used with the next request
         echo GenerateTopPanel($aliq);
         $aliquot_settings = array_merge($aliq, $aliquot_settings);
         return 0;
      }
   }
   else{ //we dont have the settings, create the interface for the settings
?>
   <div id="aliquot_settings">
   <table>
      <tr><td>Parent Sample Format</td><td><input type='text' id="parent_format" name='aliquot_settings[parent_format]' size="15" /></td>
         <td>Aliquot Format</td><td><input type='text' id="aliquot_format" name='aliquot_settings[aliquot_format]' size="15" /></td>
         <td>No of Aliquots</td><td><input type='text' name='aliquot_settings[aliquot_number]' size="3" id='aliquot_number_id' onBlur='Samples.generateAliquots();' /></td></tr>
      <tr id='aliquotNos'>&nbsp;</tr>
   </table>
      <div id='links'><input type='button' name='find' value='Save' onclick="Samples.search();" />
          <input type='button' name='cancel' value='Cancel' /></div>
   </div>
<?php
      return 1;
   }
}

/**
 *Generates the top panel with the settings currentrly being used
 *
 * @param array $aliq   An array with the data which the user has already defined
 * @return HTML   Returns the HTML code that will be used to create this top panel
 */
function GenerateTopPanel($aliq){
global $aliquot_settings;
$aliq['aliquot_format'] = strtoupper($aliq['aliquot_format']);
$parts = explode(' ', $aliq['aliquot_format']);
$aliquot_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
$aliq['parent_format'] = strtoupper($aliq['parent_format']);
$parts = explode(' ', $aliq['parent_format']);
$parent_format = trim($parts[0]).'[0-9]{'."$parts[1]}";
$no_of_aliquots = $aliq['aliquot_number'];
$aliquot_settings['parent_format2use'] = $parent_format;
$aliquot_settings['aliquot_format2use'] = $aliquot_format;
$aliquot_settings['trays_format2use'] = array();
$aliquot_settings['just_trays'] = array();

$top_panel = <<< TopPanel
<div id='aliquot_settings'>
   <table>
      <tr><td>Parent Format:<b>{$aliq['parent_format']}</b><input type='hidden' name='aliquot_settings[parent_format]' value='{$aliq['parent_format']}' /></td>
         <td>&nbsp;&nbsp;Aliquot Format:<b>{$aliq['aliquot_format']}</b><input type='hidden' name='aliquot_settings[aliquot_format]' value='{$aliq['aliquot_format']}' /></td>
         <td>&nbsp;&nbsp;No of Aliquots:<b>$no_of_aliquots</b><input type='hidden' name='aliquot_settings[aliquot_number]' value='$no_of_aliquots' /></td></tr>
      <tr id='aliquotNos'><td colspan="3"><table>
TopPanel;

   for($i=0; $i<count($aliq['trays']); $i++){
      $t=$aliq['trays'][$i];
      $parts = explode(' ', $t['name']);
      $trayName =  trim($parts[0]).'[0-9]{'."$parts[1]}";
      $aliquot_settings['trays_format2use'][] = $trayName;
      $aliquot_settings['just_trays'][] = $t['name'];
      $top_panel .= "<tr><td>Tray:<b>{$t['name']}<input type='hidden' name='aliquot_settings[trays][$i][name]' value='{$t['name']}' /></b></td>"
      ."<td>Description:<b>{$t['descr']}<input type='hidden' name='aliquot_settings[trays][$i][descr]' value='{$t['descr']}' /></b></td>\n"
      ."<td>Size:<b>{$t['size']}<input type='hidden' name='aliquot_settings[trays][$i][size]' value='{$t['size']}' /></b></td></tr>\n";
   }
$top_panel .= <<< TopPanel
   </table></td></tr>
   </table>
</div>
TopPanel;

return $top_panel;
}
//=============================================================================================================================================

/**
 * Deletes a saved aliquot from the saved searches
 *
 * @global array $config   The config to use to connect to the database.
 * @global string $query   A global query string
 * @global string $contact A string with additional error information
 * @return none   This function returns nothing. It calls other function which do the dirty work of outputting to the screen
 */
function DeleteAliquot(){
global $config, $query, $contact;
   $res=Connect2DB($config);
   if(is_string($res)) {
      SampleProcessing("<div class='error'>There was an error while connecting to the database.$contact</div>");
      return;
   }
   $aliquot_format = strtoupper($_POST['aliquot_settings']['aliquot_format']);
   $parts = explode(' ', $aliquot_format);
   $aliquot_format = trim($parts[0]).'[0-9]{'."$parts[1]}";

   if(!preg_match('/^'.$aliquot_format.'$/i', $_POST['aliquot2delete'])){
      SampleProcessing("<div class='error'>{$aliquot_format}Unkown aliquot format Cannot delete the aliquot '{$_POST['aliquot2delete']}'.</div>");
      return;
   }
   $res = DeleteItem("{$config['temp_dbase']}.aliquots", 'label', $_POST['aliquot2delete']);
   if(is_string($res)){
      SampleProcessing("<div class='error'>There was an error while deleting the aliquot '{$_POST['aliquot2delete']}'. It has not been deleted.</div>");
      return;
   }
   else{
      SampleProcessing("<div class='no_error'>Successfully deleted the aliquot '{$_POST['aliquot2delete']}'.</div>");
      return;
   }
}
//=============================================================================================================================================
?>