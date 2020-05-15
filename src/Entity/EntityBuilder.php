<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 21/06/18
 * Time: 17.09
 */

namespace Tabusoft\ORM\Entity;


use Aura\Cli\Exception;
use Tabusoft\ORM\Configurator\Configurator;
use Tabusoft\ORM\EQB\EQBException;
use Tabusoft\ORM\Repository\RepositoryAbstract;

class EntityBuilder
{
    private $properties = [];

    private $table_name;

    private $table_type;

    private $is_view;

    private $entity_name;

    private $entity_descriptor_name;

    private $repository_name;

    private $repository_descriptor_name;

    private $namespace;

    private $entities_namespace;

    private $entities_descriptor_namespace;

    private $repositories_namespace;

    private $repositories_descriptor_namespace;

    private $directory;

    private $primary_key;

    private $database;

    private $database_hash;

    private $host;

    private $port;

    private $relations = [];

    private $use = [];

    public function __construct($db, $database_name, $database_hash, $database_host, $database_port, $table_name, $table_type, $namespace, $directory)
    {

        $this->database = $database_name;

        $this->db = $db;

        $this->table_name = $table_name;
        $this->table_type = $table_type;

        $this->directory = $directory;
        $this->namespace = $namespace;

        $this->database_hash = $database_hash;
        $this->host = $database_host;
        $this->port = $database_port;

        $this->is_view = ($table_type === 'VIEW');

        $this->createNames();

        $this->createProperties();

        $this->createRelations();
    }

    private function createNames()
    {


        if ($this->namespace !== "") {
            $this->namespace = trim($this->namespace, "\\") . "\\";
        }

        $this->entity_name = RepositoryAbstract::createEntityName($this->table_name);
        $this->entity_descriptor_name = $this->entity_name . "Descriptor";

        $this->repository_name = $this->entity_name . "Repository";
        $this->repository_descriptor_name = $this->entity_name . "RepositoryDescriptor";

        $this->entities_namespace = $this->namespace . "Entities";
        $this->entities_descriptor_namespace = $this->namespace . "Entities\\Descriptors";

        $this->repositories_namespace = $this->namespace . "Repositories";
        $this->repositories_descriptor_namespace = $this->namespace . "Repositories\\Descriptors";


    }

    private function createProperties()
    {
        $res = $this->db->query("DESCRIBE {$this->table_name}");

        $this->primary_key  = false;
        $id_is_present = false;

        foreach ($res as $property) {
            if ($property["Key"] === 'PRI') {
                $this->primary_key  = $property["Field"];
            }
            if ($property["Field"] === 'id') {
                $id_is_present = true;
            }
            $property["orm_type"] = $this->findType($property['Type'], Configurator::$dataTypes);
            $property["php_type"] = $this->findType($property['Type'], Configurator::$php_dataTypes);
            if($property["Null"] === "YES"){
                $property["nullable"] = true;
            }
            else if ($property["Key"] === 'PRI') {
                $property["nullable"] = true;
            }
            else{
                $property["nullable"] = false;
            }

            $this->properties[] = $property;
        }

        if (!$this->primary_key ) {
            if (!$this->is_view) {
                throw new \Exception("It's necessary a primary key named ID in {$this->table_name}");
            } else {
                if (!$id_is_present) {
                    echo PHP_EOL . "E' necessaria una colonna `id` da usare come primary nella VIEW: '{$this->table_name}'" . PHP_EOL;
                } else {
                    $this->primary_key = 'id';
                }
            }
        }

    }

    public function createRelations()
    {
        $qres = $this->db->query("SELECT * 
                      FROM information_schema.`KEY_COLUMN_USAGE`
                      WHERE TABLE_SCHEMA = ?
                        AND TABLE_NAME = ?
                        AND NOT ISNULL(REFERENCED_TABLE_NAME) 
                  ", [$this->database, $this->table_name]);

        foreach ($qres as $relation) {
            if ($relation["REFERENCED_TABLE_SCHEMA"] !== $this->database) {
                continue;
            }
            $this->relations[$relation["REFERENCED_TABLE_NAME"]] = [
                "from" => $this->table_name,
                "to" => $relation["REFERENCED_TABLE_NAME"],
                "from_column" => $relation["COLUMN_NAME"],
                "to_column" => $relation["REFERENCED_COLUMN_NAME"],
                "className" => RepositoryAbstract::createEntityName($relation["REFERENCED_TABLE_NAME"]),
                "id" => $relation["COLUMN_NAME"]
            ];

            $this->use[] = $this->entities_namespace."\\".RepositoryAbstract::createEntityName($relation["REFERENCED_TABLE_NAME"]);


        }

        $this->use = array_unique($this->use);

    }

    public function renderEntityDescriptor()
    {
        ob_start();
        include(__DIR__ . "/../../view/ORM/EntityDescriptor.phtml");
        $content = ob_get_clean();
        file_put_contents($this->getFilePath("", "EntityDescriptor"), $content);
    }

    public function getFilePath($position, $what)
    {

        $directories[] = APP_DIR;
        $directories[] = trim($this->directory, "\n\t");

        if ($position !== '') {
            $directories[] = $position;
        }

        switch ($what) {
            case 'Entity':
                $directories = array_merge($directories, ["Entities"]);
                $file = $this->entity_name;
                break;
            case 'EntityDescriptor':
                $directories = array_merge($directories, ["Entities","Descriptors"]);
                $file = $this->entity_descriptor_name;
                break;
            case 'Repository':
                $directories = array_merge($directories, ["Repositories"]);
                $file = $this->repository_name;
                break;
            case 'RepositoryDescriptor':
                $directories = array_merge($directories, ["Repositories", "Descriptors"]);
                $file = $this->repository_descriptor_name;
                break;
            default:
                throw new \Exception("invalid type: {$what}");
        }

        $file .= '.php';

        $directory = implode("/", $directories);
        $directory = str_replace("//", "/", $directory);

        if (!file_exists($directory)) {
            mkdir($directory, 0770, true);
        }

        return $directory . "/" . $file;

    }

    public function renderEntity()
    {
        ob_start();
        include(__DIR__ . "/../../view/ORM/Entity.phtml");
        $content = ob_get_clean();
        file_put_contents($this->getFilePath("", "Entity"), $content);
    }

    public function renderRepository()
    {
        ob_start();
        include(__DIR__ . "/../../view/ORM/EntityRepository.phtml");
        $content = ob_get_clean();
        file_put_contents($this->getFilePath("", "Repository"), $content);
    }

    public function renderRepositoryDescriptor()
    {
        ob_start();
        include(__DIR__ . "/../../view/ORM/EntityRepositoryDescriptor.phtml");
        $content = ob_get_clean();
        file_put_contents($this->getFilePath("", "RepositoryDescriptor"), $content);
    }

    private function findType($type, array $dataTypes)
    {
        $type = strtoupper(trim(preg_replace("/\\(.*\\).*/i", "", $type)));
        if(!isset($dataTypes[$type])){
            throw new \Exception("can't find type for $type");
        }

        return $dataTypes[$type];

    }

}
