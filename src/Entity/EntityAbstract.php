<?php

namespace Tabusoft\ORM\Entity;

use Tabusoft\ORM\Repository\RepositoryAbstract;

class EntityAbstract implements \JsonSerializable{

    private static $repoCache = [];
    private static $lazy_cache = [];

    public function __get($name)
    {
        if(!isset($name)){
            throw new \Exception('Can\'t find: '.$name);
        }
        return $this->{$name};
    }

    public function __set($name, $value)
    {
        return $this->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->{$name});
    }

    public function jsonSerialize()
    {
        return json_encode( get_object_vars($this) );
    }

    public static function getRepository(): RepositoryAbstract {
        static $repoCache = [];
        $info = explode("\\",get_called_class());
        $repo = $info[0]."\\Repositories\\".$info[2]."Repository";
        if( isset($repoCache[$repo])){
            return $repoCache[$repo];
        }

        return $repoCache[$repo] = new $repo;
    }

    public function lazyLoad($class, $id){
        if($id === null){
            throw new \Exception("can't lazy load {$class} of id: ".( ($id==null)?"null":$id) );
        }


        if(!empty(self::$lazy_cache[$class][$id])){
            return self::$lazy_cache[$class][$id];
        }

        /**
         * @var $repository RepositoryAbstract
         */
        $repository = call_user_func($class."::getRepository");
        if(!isset(self::$lazy_cache[$class])){
            self::$lazy_cache[$class] = [];
        }

        self::$lazy_cache[$class][$id] =  $repository->findById($id);
        return self::$lazy_cache[$class][$id];
    }
    
}