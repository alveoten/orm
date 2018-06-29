<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 20/06/18
 * Time: 10.52
 */

namespace Tabusoft\ORM\Configurator;

use Tabusoft\CliManager\CliManager;

class CreateConfig
{
    static private $options = [
        'mysql' => "select the type of server as MySQL, DEFAULT",
        'mongodb' => "select the type of server as MongoDB - OPTIONAL",
        'host,h:' => "The hostname - REQUIRED",
        'user,u:' => "The db username - REQUIRED",
        'password,p:' => "The db username password - REQUIRED",
        'port:' => "The db port, default 3306 - OPTIONAL",
        'database-name,d:' => "The database name - REQUIRED",
        'namespace,n:' => "The namespace of the entities - REQUIRED",
        'entity-directory,e:' => "The root directory where to put the Entity - REQUIRED",
        'output-file,o:' => "The output file, std output if not given - OPTIONAL",
        'php-array' => "Set the output to php, instead default JSON",
        'help' => "this help - OPTIONAL"
    ];

    public function main()
    {

        $cli = new CliManager(
            self::$options,
            "Cli to manage the creation of entity and repository",
            './create-config --mysql --host=localhost --user=root --password=password --port=3306 --database-name=mydb --output-file=destination.json --output-type=json',
            "A multi-line description of the command.",
            [ '-h', '-u', '-p', '-d', '-e', '-n' ],
            [ ["--mongodb", "--mysql"] ]
        );

        $get_opt = $cli->getOpt();
        $validity = $cli->getValidity();

        if (!$validity OR isset($get_opt["--help"])) {
            if (!$validity) {
                $cli->outln("<<red>>{$cli->getError()}<<reset>>");
            }
            $cli->outHelp();
            exit;
        }

        $array = $this->create($get_opt);

        if(isset($get_opt["--php-array"])){
            $str = $this->toPHP($array);
        }
        else{
            $str = json_encode($array,JSON_PRETTY_PRINT);
        }

        if(isset($get_opt["-o"])){
            file_put_contents($get_opt["-o"],$str);
            return;
        }

        $cli->outln($str);

    }



    private function create($opt)
    {
        if (isset($opt["--mongodb"])) {
            return $this->createMongoDB($opt);
        }

        return $this->createMySQL($opt);
    }

    private function createMySQL($options)
    {

        return [
            $options['--database-name'] => [
                'database-name' => $options['--database-name'],
                'username' => $options['--user'],
                'password' => $options['--password'],
                'host' => $options["--host"],
                'port' => $options['--port']??"3306",
                'entity-directory' => $options['--entity-directory'],
                'namespace' => $options["--namespace"]
            ]
        ];

    }

    private function createMongoDB($options){
        return [
            $options['--database-name'] => [
                'database-name' => $options['--database-name'],
                'username' => $options['--username'],
                'password' => $options['--password'],
                'host' => $options["--host"],
                'port' => $options['--port'],
                'entity-directory' => $options['--entity-directory'],
                'namespace' => $options["--namespace"]
            ]
        ];
    }

    private function toPHP($array){
        return "<?php".PHP_EOL.PHP_EOL."return ".var_export($array, true).";";
    }

}