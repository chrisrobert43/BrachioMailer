<?php
/**
 * DB singleton class.
 */
class DB {

    /**
     * PDO object
     */
    private static $pdo;

    /**
     * Create a new PDO object.
     * Note: If your application does not catch the exception thrown from the PDO constructor, 
     * the default action taken by the zend engine is to terminate the script and display a back trace. 
     * This back trace will likely reveal the full database connection details, including the username and password.
     *
     * @param string $databasename
     * @return bool
     */
    public static function CreateDBConnection($databasename = DBNAME)
    {
        $pdo_options = array();
        if (DBDRIVER === 'mysql') {
            $pdo_options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
            if (!defined('MYSQL_VERSION_NATIVE_PREPARE_CACHE_SUPPORT')) {
                define('MYSQL_VERSION_NATIVE_PREPARE_CACHE_SUPPORT', '5.1.17');
            }
        }

        try {
            self::$pdo = new PDO(DBDRIVER . ':host=' . DBSERVER . ';port=' . DBPORT . ';dbname=' . $databasename, DBUSER, DBPASSWORD, $pdo_options);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (DBDRIVER === 'mysql') {
                // Set to true to allow multiple queries in one prepare block
                //self::$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

                // Set prepared statement emulation depending on server version
                $use_emulate_prepares = (version_compare(self::$pdo->getAttribute(PDO::ATTR_SERVER_VERSION), MYSQL_VERSION_NATIVE_PREPARE_CACHE_SUPPORT, '<'));
                self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $use_emulate_prepares);
            }
        } catch (PDOException $pdoexc) {
            error_log('DB exception: '.$pdoexc->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Get the PDO object.
     *
     * @return mixed PDO object or false on error
     */
    public static function getDBConnection()
    {
        if (empty(self::$pdo)) {
            if (!self::CreateDBConnection()) {
                return false;
            }
        }

        return self::$pdo;
    }

    /**
     * Close the database connection by nulling the PDO object.
     */
    public static function CloseDBConnection()
    {
        self::$pdo = NULL;
    }

    /**
     * Override the magic __debugInfo method (new in PHP 5.6.0) because
     * if the method isn't defined on an object, then ALL public, protected and private properties will be shown.
     */
    public function __debugInfo()
    {
        return array('error' => '__debugInfo disabled.');
    }

    /**
     * Override the magic __toString method.
     */
    public function __toString()
    {
        return '__toString disabled.';
    }
}
