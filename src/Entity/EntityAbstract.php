<?php

namespace Tabusoft\ORM\Entity;

use Tabusoft\ORM\Repository\RepositoryAbstract;

class EntityAbstract implements \JsonSerializable{

    private static $repoCache = [];
    private static $lazy_cache = [];

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        $repo = self::getRepository();
        $column = $repo->getTableColumnsDescription();
        $return = [];
        foreach ($column as $k => $descr){
            $return[$k] = $this->{$k};
        }
        return $return;
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