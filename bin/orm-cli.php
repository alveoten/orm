#!/usr/bin/php
<?php

use Tabusoft\ORM\Configurator\Configurator;

define("APP_DIR", realpath( getcwd() ) );

chdir(APP_DIR);

// Composer autoloading
require 'vendor/autoload.php';

$configurator = new Configurator();
$configurator->main();

exit;

/*$options = getopt('', [
    'database:',
    'username:',
    'password:',
    'help',
    'module-name:',
    'table-name:',
    'overwrite-entities',
    'force',
    'skip-entities',
    'skip-repository',
    'skip-abstracts'
]);
*/

$options_needed = [
    'database' => null,
    'module-name' => function ($value) {
        $GLOBALS["dir_needed"] = APP_DIR . "/module/" . $value . "/src/Entity/";

        if (!file_exists($GLOBALS["dir_needed"]))
            return "impossibile trovare: " . $GLOBALS["dir_needed"].PHP_EOL;

        $GLOBALS["dir_needed_repository"] = APP_DIR . "/module/" . $value . "/src/Repository/";
        if (!file_exists($GLOBALS["dir_needed_repository"]))
            return "impossibile trovare: " . $GLOBALS["dir_needed_repository"].PHP_EOL;

        $GLOBALS["dir_needed_repository_abstract"] = APP_DIR . "/module/" . $value . "/src/Repository/Abstracts/";
        if (!file_exists($GLOBALS["dir_needed_repository_abstract"]))
            return "impossibile trovare: " . $GLOBALS["dir_needed_repository_abstract"].PHP_EOL;

        return true;
    },
];

if (count($options) == 0) {
    echo PHP_EOL . "USAGE: php orm.php --username=root --password=Af40jC --database=gilterdon --host=localhost --module-name=Forum" . PHP_EOL .
            PHP_EOL . "parametri non validi." .
            PHP_EOL . 'E\' necessario fornire il database, e assicurarsi che config sia configurato correttamente' . PHP_EOL .
            PHP_EOL . 'solo una tabella: table-name' . PHP_EOL .
            PHP_EOL . 'modulo di destinazione: module-name' . PHP_EOL .
            PHP_EOL . 'forza sovrascrittura entita\': force' . PHP_EOL .
            PHP_EOL . 'salta entita\': skip-entities' . PHP_EOL .
            PHP_EOL . 'salta repositori: skip-repository' . PHP_EOL .
            PHP_EOL . 'salta le abstract: skip-abstracts';
    exit;
}

foreach ($options_needed as $opt => $validator) {
    if (!isset($options[$opt])) {
        echo PHP_EOL . "manca: --{$opt} "
            . PHP_EOL . PHP_EOL;
        exit;
    }

    if (is_callable($validator) AND ($res = $validator($options[$opt])) !== true) {
        echo PHP_EOL . "{$opt}: {$res}";
        exit;
    }
}


\ORM\DB\DBFactory::$config = [
    'orm_db' =>
        [
            $options['database'] => [
                'database' => $options['database'],
                'username' => $options['username'],
                'password' => $options['password'],
                'host' => 'localhost'
            ]
        ]
];

$db = \ORM\DB\DBFactory::getInstance($options['database']);

$tables = $db->query("SHOW FULL TABLES;");

foreach ($tables as $table) {

    if(isset($options['table-name']) AND $options['table-name'] !== $table[0]){
        continue;
    }


    $entity = [];

    $entity['table_real'] = $table[0];
    $is_view = ($table[1] === 'VIEW');

    $tableName = explode("_", $entity['table_real']);
    for ($i = 0; $i < count($tableName); $i++) {
        $tableName[$i] = strtolower($tableName[$i]);
        $tableName[$i] = ucfirst($tableName[$i]);
    }
    $className = implode("", $tableName);
    $repositoryName = $className . "Repository";
    $repositoryNameAbstract = $className . "RepositoryAbstract";

    try {
        $res_properties = $db->query("DESCRIBE {$entity['table_real']}");
    } catch (Exception $e) {
        var_dump($e);
        exit;
    }

    $database = $options["database"];



    $properties = [];
    $primary_key = false;
    $id_is_present = false;


    foreach ($res_properties as $property) {
        $property["orm_type"] = findORMType($property['Type']);

        $properties[] = $property;
        if ($property["Key"] === 'PRI') {
            $primary_key = $property["Field"];
        }
        if($property["Field"] === 'id'){
            $id_is_present = true;
        }
    }

    if (!$primary_key) {
        if(!$is_view) {
            echo PHP_EOL . "E' necessaria una colonna primary nella tabella: '{$entity['table_real']}'" . PHP_EOL;
            exit;
        }
        else{
            if(!$id_is_present){
                echo PHP_EOL . "E' necessaria una colonna `id` da usare come primary nella VIEW: '{$entity['table_real']}'" . PHP_EOL;
            }
            else{
                $primary_key = 'id';
            }
        }
    }

    //create entity
    $entity['namespace'] = $options['module-name']."\\Entity";
    $entity['namespace_complete'] = "namespace ".$options['module-name']."\\Entity";
    $entity['traitName'] = $classNameTrait;
    $entity['className'] = $className;

    ob_start();
    include(APP_DIR . "/module/ORM/view/ORM/Entity.phtml");
    $content = ob_get_clean();
    if(!isset($options['skip-entities'])){
        alertWrite(
            $GLOBALS["dir_needed"] . $className . '.php',
            "Nel modulo {$options["module-name"]} è già presente l'entità: $className, vuoi sovrascriverla?",
            $content,
            $options
        );
    }

    //create repositoryAbstract
    $repositoryAbstract = [
        "namespace" => $options['module-name']."\\Repository\\Abstracts",
        "namespace_complete" => "namespace ". $options['module-name']."\\Repository\\Abstracts",
        "classname" => $className."RepositoryAbstract",
        "primary_key" => $primary_key,
        "is_view" => $is_view
    ];

    ob_start();
    include(APP_DIR."/module/ORM/view/ORM/EntityRepositoryAbstract.phtml");
    $content = ob_get_clean();
    if(!isset($options['skip-abstracts'])) {
        alertWrite(
            $GLOBALS["dir_needed_repository_abstract"] . $className . 'RepositoryAbstract.php',
            "Nel modulo {$options["module-name"]} è già presente il RepositoryAbstract: {$className}RepositoryAbstract, vuoi sovrascriverlo?",
            $content,
            $options
        );
    }

    //create repository
    $repository = [
        'namespace' => $options['module-name']."\\Repository",
        'namespace_complete' => "namespace ".$options['module-name']."\\Repository",
        'use' => 'use '.$repositoryAbstract['namespace']."\\".$repositoryAbstract['classname'],
        'classname' => $className.'Repository',
        'extends' => $repositoryAbstract['classname']
    ];
    ob_start();
    include(APP_DIR."/module/ORM/view/ORM/EntityRepository.phtml");
    $content = ob_get_clean();
    if(!isset($options['skip-repository'])) {
        alertWrite(
            $GLOBALS["dir_needed_repository"] . $className . 'Repository.php',
            "Nel modulo {$options["module-name"]} è già presente il repository: {$className}Repository, vuoi sovrascriverlo?",
            $content,
            $options
        );
    }

}//foreach table;

function readChars()
{
    $chars = '';
    while (true) {
        $char = fgetc(STDIN);

        if ($char === PHP_EOL) {
            break;
        }

        $chars .= $char;
    }
    return trim($chars);
}

function alertWrite($filePath,
                    $message,
                    $content,
                    $options){
    $write = false;
    if (!file_exists($filePath) OR isset($options['force']) ) {
        $write = true;
    } else {
        echo PHP_EOL . $message . PHP_EOL . "(y/n):";
        do{
            $read = readChars();
            if(!in_array(strtolower($read),["y","n"])){
                echo PHP_EOL . "(y/n):";
                continue;
            }

            if($read === "y"){
                $write = true;
                echo PHP_EOL."sovrascrivo ".$filePath;
            }

            break;
        }while(true);
    }
    if ($write) {
        file_put_contents($filePath,"<?php".PHP_EOL.$content);
    }
}

