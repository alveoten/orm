<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 19/06/18
 * Time: 17.20
 */

namespace Tabusoft\ORM\Configurator;


use Tabusoft\CliManager\CliManager;
use Tabusoft\DB\DBFactory;
use Tabusoft\DB\DBFactoryConfig;
use Tabusoft\ORM\Entity\EntityBuilder;

class Configurator
{
    static $dataTypes = [

            "CHAR" => "text",
            "VARCHAR" => "text",
            "TINYTEXT" => "text",
            "TEXT" => "text",
            "MEDIUMTEXT" => "text",
            "LONGTEXT" => "text",
            "BINARY" => "text",
            "VARBINARY" => "text",

            "TINYINT" => "integer",
            "SMALLINT" => "integer",
            "MEDIUMINT" => "integer",
            "INT" => "integer",
            "INTEGER" => "integer",
            "BIGINT" => "integer",

            "BIT" => "numeric",
            "DECIMAL" => "numeric",
            "DEC" => "numeric",
            "NUMERIC" => "numeric",
            "FIXED" => "numeric",
            "FLOAT" => "numeric",
            "DOUBLE" => "numeric",
            "REAL" => "numeric",
            "BOOL" => "numeric",
            "BOOLEAN" => "numeric",

            "DATE" => "datetime",
            "DATETIME" => "datetime",
            "TIMESTAMP" => "datetime",
            "TIME" => "datetime",
            "YEAR" => "datetime",

            "TINYBLOB" => "blob",
            "BLOB" => "blob",
            "MEDIUMBLOB" => "blob",

            "ENUM" => "text",
            "SET" => "text"
    ];

    static $php_dataTypes = [
        "CHAR" => "string",
        "VARCHAR" => "string",
        "TINYTEXT" => "string",
        "TEXT" => "string",
        "MEDIUMTEXT" => "string",
        "LONGTEXT" => "string",
        "BINARY" => "string",
        "VARBINARY" => "string",
        "ENUM" => "string",
        "SET" => "string",

        "TINYINT" => 'int',
        "SMALLINT" => 'int',
        "MEDIUMINT" => 'int',
        "INT" => 'int',
        "INTEGER" => 'int',
        "BIGINT" => 'int',
        "BIT" => 'int',
        "DECIMAL" => 'int',
        "DEC" => 'int',
        "NUMERIC" => 'int',
        "FIXED" => 'int',


        "FLOAT" => 'float',
        "DOUBLE" => 'float',
        "REAL" => 'float',

        "BOOL" => 'bool',
        "BOOLEAN" => 'bool',

        "DATE" => "string",
        "DATETIME" => "string",
        "TIMESTAMP" => "string",
        "TIME" => "string",
        "YEAR" => "string",

        "TINYBLOB" => "string",
        "BLOB" => "string",
        "MEDIUMBLOB" => "string",
    ];


    private $config;
    private $db;

    public function main()
    {
        $cli = $this->getCliManager();

        $option = $cli->getOpt();

        if (!$cli->getValidity() or !count($GLOBALS["argv"]) ) {
            $cli->outError();
            $cli->outHelp();
            exit;
        }

        $this->config = json_decode(file_get_contents($option[2]));

        foreach(["--table-name","--database"] as $opt) {
            if (isset($option[$opt])) {
                $option[$opt] = explode(",", $option[$opt]);
            }
        }

        if(isset($option["--database"])){
            $dbs = $option["--database"];
        }
        else{
            $dbs = array_keys((array)$this->config);
        }

        foreach($this->config as $db_name => $db_info) {

            $this->do($db_info,$option,$option[1]);
        }

        $this->outLn("");
    }

    private function getCliManager(): CliManager
    {
        return new CliManager(
            [
                '#arg1' => "update or install to decide the type of operation",
                '#arg2' => "specify the position of JSON config file",
                '--table:' => "if set, one or more comma separated, the script will work only on those tables",
                "--database:" => "if set, one or more comma separated, the script only work only on this db config part"
            ],
            "Cli to manage the creation of entities and repositories",
            './orm-cli install config.json' . PHP_EOL . './orm-cli update config.json',
            "You can use install to create all files, and update just tu update entities field, and repository declaration",
            [1, 2],
            [],
            [
                1 => function ($val) {
                    if ($val !== 'install' AND $val !== 'update') {
                        return "not valid command, must be install or update";
                    }
                    return true;
                },
                2 => function ($val) {
                    if (!file_exists($val)) {
                        return "not valid position for the file: {$val}, not found";
                    }
                    json_decode(file_get_contents($val));
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return "not valid json in {$val}";
                    }
                    return true;
                }
            ]
        );
    }

    private function injectDBConfig()
    {

        foreach($this->config as $db_name => $infos){

            $db = DBFactory::getInstance( new DBFactoryConfig(
                    $this->config->{$db_name}->host,
                    $this->config->{$db_name}->{"database-name"},
                    $this->config->{$db_name}->username,
                    $this->config->{$db_name}->password,
                    $this->config->{$db_name}->port
                )
            );

        }
    }

    /**
     * @param $db_info
     * @param $options
     * @param $command_type
     */
    private function do($db_info, $options, $command_type)
    {

        $db_config = new DBFactoryConfig($db_info->host,$db_info->{"database-name"}, $db_info->username, $db_info->password, $db_info->port);
        $db = DBFactory::getInstance($db_config);

        $tables = $db->query("SHOW FULL TABLES;");
        foreach($tables as $table){
            if(isset($options["--table-name"]) AND !in_array($table[0], $options["--table-name"])){
                continue;
            }

            $this->outLn("\033[1;33m{$table[0]}\033[0m");

            $db_name = $db_info->{"database-name"};

            $entity = new EntityBuilder($db,
                                        $db_info->{"database-name"},
                                        $db_config->getHash(),
                                        $db_config->getHost(),
                                        $db_config->getPort(),
                                        $table[0],
                                        $table[1],
                                        $this->config->{$db_name}->namespace,
                                        $this->config->{$db_name}->{"entity-directory"}
            );

            if($command_type == "install") {
                $this->outLn("\tCreate Entity");
                $entity->renderEntity();
                $this->outLn("\tCreate Repository");
                $entity->renderRepository();
                $this->outLn("\tCreate Entity Descriptor");
                $entity->renderEntityDescriptor();
                $this->outLn("\tCreate Repository Descriptor");
                $entity->renderRepositoryDescriptor();
            }
            else{
                $this->outLn("\tUpdate Entity Descriptor");
                $entity->renderEntityDescriptor();
                $this->outLn("\tUpdate Repository Descriptor");
                $entity->renderRepositoryDescriptor();
            }

        }

    }

    private function findORMType($type)
    {
        $dataTypes = Configurator::$dataTypes;
        $type = strtoupper(trim(preg_replace("/\\(.*\\).*/i", "", $type)));
        $orm_type = null;
        foreach ($dataTypes as $orm_type => $mysql_types) {
            if (in_array($type, $mysql_types)) {
                return $orm_type;
            }
        }
        return "NOT_FOUND";
    }

    private function outLn($message){
        echo PHP_EOL.$message;
    }
}
