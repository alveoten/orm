# Tabusoft/ORM

Tabusoft/ORM is an entity and relationship manager.

- Simply create Entities and Repositories with command line script
- Help you to build query entity based. You will use Entities to get Entities, you don't need to convert query output to entities!
- All foreigns keys relations between tables will mapped, help you to write queries, or simply lazy load the related entities.

## Installation

You can install it with composer

```sh
$ composer require tabusoft/orm
```

## Use the entity manager
To use the entity manager we must create all entities and repository. The meaning of those two words will explain in the next paragraph.
We have two command lines utilities: orm-cli and create-config. 
### Create the config.json
create-config could be use to create a config.json files to use as parameter for orm-cli.
```sh
$ ./create-config --mysql --host=localhost --user=root --password=password --port=3306 --database-name=mydb --output-file=destination.json --output-type=json
```
You may specify a lot of config options. Please read the help:
```sh
$ ./create-config --help
```

### Create the enities
The entities object are a 1 to 1 description of DB's table or collection. The class ar stored in the selected namespace under the "Entities" directory.
Entities classes use a Trait that contains all the properties and setters and getters.
Repository are use to manage the DB persistance and are stored in the selected namespace under the folder Repositories. 
Repositories classes use a Trait that contains all the info to grant the persistence.
 
To create these files you can use: orm-cli
```sh
$ ./orm-cli install config.json 
```

To only update "Descriptors" without change the user edited Entities
```sh
$ ./orm-cli update config.json 
```  

#### Entities restrictions
Some tips to create your tables on MySQL DB.
* The table must have an id column as Primary key.
* If you have a view the view must have an id unique column. Be smart with the creation.
* the relation will be create with the foreign keys. You may create your relations, after.
* The Repository can use differnt dbs from each others. But you cannot join with the EQB tables that differ in DB's host or port.

## Use the entities
For example you will create these enities: (will be used in the below examples)
```php
<?php

namespace Application\Entities;

use Tabusoft\ORM\Entity\EntityAbstract;
use Application\Entities\Descriptors\ForumCategoryDescriptor;

/**
*
* @database test_forum
* @table forum_category
*
**/
class ForumCategory extends EntityAbstract
{
    use ForumCategoryDescriptor;

    /** implements here your class methods */

}
?>
```
```php
<?php

namespace Application\Entities;

use Tabusoft\ORM\Entity\EntityAbstract;
use Application\Entities\Descriptors\ForumTopicsDescriptor;

/**
*
* @database test_forum
* @table forum_topics
*
**/
class ForumTopics extends EntityAbstract
{
    use ForumTopicsDescriptor;

    /** implements here your class methods */

}
?>
```

### Init the entitity
tabusoft/orm use massively tabusoft/db in the factory configuration you need to provide the same configuration of the tables. Please refer to [the documentation of that package](https://github.com/alveoten/db).

You can instantiate like an object:
```php
//init the tabusoft/db 
$db_config = new \Tabusoft\DB\DBFactoryConfig("localhost","test","test_username","test_password");
\Tabusoft\DB\DBFactory::getInstance($db_config);

//empty object
$category = new Application\Entities\Category();

```
Or you can retrieve it from db:
```php
//obtain the repository
$repo = Application\Entities\ForumCategory()::getRepository();

//or
$repo = new Application\Repository\ForumCategoryRepository();

$category = $repo->findById(1323);
```

### Save (insert or update) the entity
The entity manager use a MySQL REPLACE command to insert or update the row in the DB.
You can simply change your Entity properties and save it throw his repository. 
```php
$category->setCategoryName("New Category name");
$repo->save($category);
```
If you are saving a new Category object you must fill all non-nullable columns. Otherwise you will get an \Exception.

### Lazy load
When you don't need performance you can use the foreign keys relations simple invoke the object.
In the next example we have a relation from ForumTopics and ForumCategory. The lazy method appears directly in the entity descriptor.
```php
$topics = ForumTopics::getRepository()->findBy("id",[1,2,3,4]);

foreach($topics as $topic) {
    dump($topic);

    $category = $topic->getForumCategory();

    dump($category);
}
```
Every time you call the getForumCategory() method you have a query. The result will be cached. Take care if you will use multiple requests. 

## Use the query builder
EQB is the Entity Query Builder. Give you the possibility to make queries without (if you want) enter to deeply into the DB structure.
It have 3 special class: the EQB::Entity, the EQB::Column, the EQB::Function.  

### The entities
Wrap the Entities classes to one that could use into your query.

### The columns
Maps the property to the columns. If you'll use a single column into the select you must specify the alias.

### The functions
Maps the MySQL properties to the class utilities. If you'll use it in the select statement you need to specify an alias.

### Putting all together: the query 
The EQB object is the equivalent of the SQL query.

#### Simple select
Let's put all togheter:
```php
//init the query object
$query = new EQB();

//init two EQBEntity refered to the relative Entities class.
$fc = new EQBEntity(ForumCategory::class);
$ft = new EQBEntity(ForumTopics::class);

//doing a select:
//this will return two result containing:
//a ForumCategory entity, a ForumTopic entity, 
//and the id column of forum category enitity. 
$query = $q->select( $ft, $fc, $fc->id->as("id") );
$query->from($ft);
$query->where($fc->id, "IN (?)")->pushValue([1,2]);
$res = $query->query();

//or in the short mode:
$res = $q->select( $ft, $fc, $fc->id->as("id") )
            ->from($ft);
            ->where($fc->id, "IN (?)");
            ->query([1,2]);

//res is a traversable stmt PDO object.
foreach($res as $r){
    var_dump($r);
}
```

#### Full explained JOIN
To make a join:
```php
$q = $q->select($ft,$fc)
    ->from($ft)
    ->join($fc, 'ON', $ft->idCategory, '=', $fc->id)
```

#### MySQL functions
```php
$q = $q->select(EQBFunction::COUNT('*')->as('num'))
    ->from($ft)
    ->leftjoin($fc)
    ->where(EQBFunction::ISNULL($fc->id);
```

### Fast Join
As said we are mapping the foreign keys relation. In this mode we can create simple join.
For each join will be search the related entity in the from and previus join. If there is no relation we search in the opposite direction.
In that case first relation win.
```php
$q = $q->select(*)
    ->from($ft)
    ->join($fc);    
``` 






