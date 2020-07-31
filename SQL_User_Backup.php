<?php
/**
 * @author David J Martin <David.Martin@nc.gov>
 *
 * Extracts users and grants from an instance of MySQL or MariaDB
 *
 * Update History
 *
 * 06 Mar 2020 - djm - First release
 * 31 Jul 2020 - djm - Fixed error in parms passed to the new PDO call
 *                     Corrected variable used in final echo with the
 *                     count of grants backed up
 *
 ***********************************************************************/
declare(strict_types=1);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

//
// You will want to modify the 5 definitions below for your environment
//

define('DBUSER'       , 'root');               // DB user with authority to SHOW GRANTS from mysql.user
define('DBPASSWORD'   , 'password');           // password for the DB user
define('USEROUTFILE'  , '/temp/Users.sql');    // where to write the user file that may be imported on new server
define('GRANTOUTFILE' , '/temp/Grants.sql');   // where to write the grant file that may be imported on new server
define('HOSTNAME'     , 'localhost');          // host name of the SQL server (MySQL or MariaDB)
$ignore_users = ['root'];                      // array of users that should NOT be exported

//
// There really should not be any reason to modify anything below this comment
// but please do browse through it and understand what is being done
//

$dsn = 'mysql:host=' . HOSTNAME . ';charset=utf8mb4';
$opt = [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION ,
	    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC       ,
		PDO::ATTR_EMULATE_PREPARES   => true                   ,
	   ];
try {

    $ourdb = new PDO ($dsn,DBUSER,DBPASSWORD,$opt);

} catch (PDOException $e) {

    error_log('Error ' . $e->getCode() . ' on line ' .
              $e->getLine() . ' in '      .
              $e->getFile() . ' -> '      .
              $e->getMessage()); // log the error so it may be looked at later

    echo 'Could not connect to the SQL server';
    exit;
}  // end of the try/catch block

//
// We got connected to the database so now let's make sure we can open the
// output files for writing - note that using mode w will overwrite any
// existing files so we'll always start off cleanly
//

$userout = fopen(USEROUTFILE,'w');

if ($userout === false) {  // could not open the output file for writing for some reason

    echo 'Could not open the output file for writing (' . USEROUTFILE . ')';
    error_log('Could not open the output file for writing (' . USEROUTFILE . ')');
    exit;

}  // end of if we could not open the output file for writing

$grantout = fopen(GRANTOUTFILE,'w');

if ($grantout === false) {  // could not open the output file for writing for some reason

    echo 'Could not open the output file for writing (' . GRANTOUTFILE . ')';
    error_log('Could not open the output file for writing (' . GRANTOUTFILE . ')');
    exit;

}  // end of if we could not open the output file for writing

$query = $ourdb->query("
	SELECT CONCAT('SHOW GRANTS FOR ''', user, '''@''', host, ''';') AS query
		   FROM mysql.user
		   WHERE user NOT IN(" . implode(',',array_map('add_quotes',$ignore_users)) . ")
");
$users = $query->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $GrantQ) {  // go through each of the users found

	$UserQ  = $ourdb->query("$GrantQ");  // retrieve the grants for a user
	$grants = $UserQ->fetchAll(PDO::FETCH_COLUMN);

	foreach ($grants as $grant) {  // go through each of the grants found for this user

		if (stripos($grant,'IDENTIFIED BY PASSWORD') === false) {

			fwrite($grantout,$grant . ';' . PHP_EOL);  // write the command to actually do the grant

		} else {

			fwrite($userout,$grant . ';' . PHP_EOL);  // write the command to actually do the grant
}
		}  // end of foreach through the grants found

}  // end of foreach through the queries to show the grants for each user

fwrite($userout ,'FLUSH PRIVILEGES;' . PHP_EOL);  // make sure SQL knows about the new users and privileges
fwrite($grantout,'FLUSH PRIVILEGES;' . PHP_EOL);  // make sure SQL knows about the new users and privileges
fclose($userout);   // close our output file
fclose($grantout);  // close our output file
echo 'The grants for ' . count($users) . ' users were written to ' . USEROUTFILE . PHP_EOL;

function add_quotes($str) {return sprintf("'%s'", $str);}
