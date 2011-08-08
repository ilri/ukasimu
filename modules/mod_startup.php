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
 * This is the worker who is never recognised. Includes all the necessary files. Initializes a session if need be. Processes the main GET or POST
 * elements and includes necessary files. Calls the necessary functions/methods
 * 
 * @category   Aliquoting
 * @package    Startup
 * @author     Kihara Absolomon <a.kihara@cgiar.org>
 * @since      v1.0
 */
define('OPTIONS_COMMON_FOLDER_PATH', '/var/www/common/');

require_once OPTIONS_COMMON_FOLDER_PATH.'mod_general_v0.4.php';
require_once 'sample_search_config';
require_once OPTIONS_COMMON_FOLDER_PATH.'mod_objectbased_dbase_v0.6.php';
require_once 'mod_sample_search.php';

$Aliquots = new Aliquots();

//lets initiate the sessions
session_save_path($Aliquots->config['session_dbase']);
session_name('sample_search');
$Aliquots->SessionStart();

//get what the user wants
$server_name=$_SERVER['SERVER_NAME'];
$queryString=$_SERVER['QUERY_STRING'];
$paging = (isset($_GET['page']) && $_GET['page']!='') ? $_GET['page'] : '';
$action = (isset($_GET['do']) && $_GET['do']!='') ? $_GET['do'] : '';
$alt_action = (isset($_POST['flag']) && $_POST['flag']!='') ? $_POST['flag'] : '';
$user = isset($_SESSION['user']) ? $_SESSION['user'] : '';

define('OPTIONS_HOME_PAGE', $_SERVER['PHP_SELF']);
define('OPTIONS_REQUESTED_MODULE', $paging);
define('OPTIONS_CURRENT_USER', $user);

/**
 * @var string    What the user wants
 */
define('OPTIONS_REQUESTED_SUB_MODULE', $action);
define('OPTIONS_REQUESTED_ACTION', $alt_action);
$t = pathinfo($_SERVER['SCRIPT_FILENAME']);
$request_type = ($t['basename']=='mod_ajax_calls.php')?'ajax':'normal';
define('OPTIONS_REQUESTED_TYPE', $request_type);

//log all the requests
if($Aliquots->logSettings['logLevel'] == 'extensive'){
   $Aliquots->Dbase->CreateLogEntry("Post User request: \n".print_r($_POST, true));
   $Aliquots->Dbase->CreateLogEntry("Get User request: \n".print_r($_GET, true));
}

//messages
define('OPTIONS_MSSG_LOGIN_ERROR', '<i>Invalid username or password, please try again.<br> If your log in details are correct, you may not have sufficient rights to access the system.<br> Please contact the System Administrator.</i>');
define('OPTIONS_MSSG_FETCH_ERROR', "Well this is embarassing! There was an error while fetching data from the %s table.$contact");
define('OPTIONS_MSSG_SAVE_ERROR', "Ooops! There was an error while saving data to the %s table.$contact");
define('OPTIONS_MSSG_DELETE_ERROR', "There was an error while deleting the entry from the %s table.$contact");
define('OPTIONS_MSSG_INVALID_NAME', "Error! Please enter a valid %s.");
define('OPTIONS_MSSG_INVALID_VARIABLE', "Error! You have input an invalid value for '%s'. Epecting a(an) %s{$contact}");
define('OPTIONS_MSSG_CREATE_DIR_ERROR', "There was an error while creating the %s directory.$contact");
define('OPTIONS_MSSG_CREATE_FILE_ERROR', "There was an error while creating the %s file.$contact");
define('OPTIONS_MSSG_MISSING_FOLDER', "The %s folder does not exists.$contact");
define('OPTIONS_MSSG_FILE_WRITE_ERROR', "There was an error while saving the data to the %s file.$contact");
define('OPTIONS_MSSG_USERREPLY_SYSTEM_ERROR','Well this is embarassing! The system is currently experiencing some problems.');

/**
 * Set the default footer links
 */
$Aliquots->footerLinks = "<a href='?'>Home</a>";

$Aliquots->TrafficController();
?>