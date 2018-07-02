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

## Use the entities
### Init the entitity

### Create, Save (insert or update) the entity

## Use the query builder
### The entities
### The columns
### The functions

### Putting all together 
#### Simple select
#### Full explained JOIN

### Fast Join






