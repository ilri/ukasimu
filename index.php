<!--
 Copyright 2011 ILRI
 
 This file is part of <aliquoter>.
 
 <aliquoter> is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 
 <aliquoter> is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with <aliquoter>.  If not, see <http://www.gnu.org/licenses/>.
-->

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Sort Samples</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel='stylesheet' type='text/css' href='css/basic.css'>
    <link rel='stylesheet' type='text/css' href='../common/common.css'>
    <link rel='stylesheet' type='text/css' href='../common/mssg_box.css'>
     <script type='text/javascript' src='../common/jquery-1.6.1.min.js'></script>
     <script type='text/javascript' src='../common/jquery.form.js'></script>
     <script type='text/javascript' src='../common/jquery.json.js'></script>
     <script type='text/javascript' src='../common/common.js'></script>
     <script type='text/javascript' src='js/sample_search.js'></script>
  </head>
  <body>
     <table id='maintable'><tr><td width="auto">
      <div id="header"><img src="images/header.jpg" alt="Sample Searching and Sorting"/></div>
      <div id="main_div"><?php require_once 'modules/mod_startup.php'; ?></div>
      <div id='footer_links'><?php echo $Aliquots->footerLinks; ?></div>
      <div id='footer'>Sort Samples</div>
      </td></tr></table>
  </body>
</html>
