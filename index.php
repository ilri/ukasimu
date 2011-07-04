<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>AVID - Field Samples Search</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel='stylesheet' type='text/css' href='basic.css'>
    <link rel='stylesheet' type='text/css' href='../common/common.css'>
    <link rel='stylesheet' type='text/css' href='../common/mssg_box.css'>
     <script type='text/javascript' src='../common/common.js'></script>
     <script type='text/javascript' src='sample_search.js'></script>
  <script type='text/javascript' src='../common/jquery.js'></script>
  <script type='text/javascript' src='../common/jquery.form.js'></script>
  <script type='text/javascript' src='../common/jquery.json.js'></script>
  </head>
  <body>
     <table id='maintable'><tr><td width="auto">
      <div id="header"><img src="images/header.jpg" alt="Avid Sample Searching and Sorting"/></div>
      <div id="main_div"><?php require_once 'main.php'; ?></div>
      <div id='footer_links'><?php echo $footerLinks?></div>
      <div id='footer'>AVID - Field Samples Search</div>
      </td></tr></table>
  <SCRIPT type="text/javascript">
     function displayExtraInfo(timestamp, lat, longitude, collector, comments, clinical, animal){
        getObject('timestamp').innerHTML="<b>"+timestamp+"</b>";
        getObject('animal').innerHTML="<b>"+animal+"</b>";
        getObject('lat').innerHTML="<b>"+lat+"</b>";
        getObject('long').innerHTML="<b>"+longitude+"</b>";
        getObject('clinical').innerHTML="<b>"+clinical+"</b>";
        getObject('collector').innerHTML="<b>"+collector+"</b>";
        getObject('comments').innerHTML="<b>"+comments+"</b>";
     }
     //set the cursor to the main input field
     getObject('searchItemId').focus();
  </SCRIPT>
  </body>
</html>