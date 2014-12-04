Database Dedupe and Remap Tool
======================

Purpose
------------

A custom command removes duplicate records from your database table.

You can easily define the uniqueness of a row using one or more columns.

It works well on large tables (10M+ rows) as well.

Installation
------------

The recommended way is through [composer](http://getcomposer.org).

Edit `composer.json` and add:

```json
{
    "require": {
        "anthonyvipond/deduper": "dev-master"
    }
}
```

And install dependencies:

```bash
    composer install
```

You should now type the command from the directory
```
./drt
```

**Removes duplicates from your table based on an array of columns defining a row's uniqueness**

Purpose
------------

###Deduplicating Tables####

Suppose you have the following table:

id | name
------------- | -------------
2  | Mary
3  | Joseph
5  | Mary
6  | mary
7  | Joseph

```
./drt dedupe tableName name
```

You will get your original table backed up, and your table will become this:

id | name
------------- | -------------
2  | Mary
3  | Joseph

----------------------------


But what if you have a table where the uniqueness of defined over two columns:

id | firstname | lastname
------------- | ------------- | -------------
2  | Mary  |  Smith
3  | Joseph  |  Parker
5  | Mary  |  Kate
6  | mary  |  kate
7  | Joseph  |  Parker

Then seperate the columns with a `:` in the second argument:

```
./drt dedupe tableName firstname:lastname
```

You will get this:

id | firstname | lastname
------------- | ------------- | -------------
2  | Mary  |  Smith
3  | Joseph  |  Parker
5  | Mary  |  Kate

----------------------------

Already have your table backed up? You can pass a **no backups** flag. It creates backups by default.

```
./drt dedupe tableName name --backups=false
```

If you don't want to see SQL queries being run, pass an --hidesql flag. It's shows SQL by default.
```
./drt dedupe tableName name --backups=false --hidesql
```

----------------------------

###Deduplicating and Remapping####

Suppose you have this `teams` table:

id | team
------------- | -------------
2  | Knicks
3  | Knicks
4  | Lakers
5  | Knicks

And you also have this `champions` table showing the Knicks won all the championships:

id | year | team_id | 
------------- | ------------- | -------------
1  | 2004 | 2
2  | 2005 | 3
3  | 2006 | 3
4  | 2007 | 5

The `champions` table links to various different Knicks records, but the Knicks are just one team.

The solution is deduplicating and remapping. Look closely:

```
./drt remap remapTable duplicatesTable team --duplicatesColumn=id --remapFkColumn=team_id
```

Like the basic dedupe command, you can also specify multiple columns to define row uniqueness.

```
./drt remap remapTable duplicatesTable firstname:lastname:birthday --duplicatesColumn=id --remapFkColumn=employee_id
```

By default both tables will be backed up for you. Disable this by passing a `--nobackups` flag

And here is your result of running the first dedupe command:

`teams` table:

id | team
------------- | -------------
2  | Knicks
4  | Lakers

`champions` table:

id | year | team_id | 
------------- | ------------- | -------------
1  | 2004 | 2
2  | 2005 | 2
3  | 2006 | 2
4  | 2007 | 2

Contribution Guidelines
------------

- I'm happy to code with you!
- Fork and make a pull request

Notes
------------

- As of right now, to use `./drt remap` both duplicates and remap table must have an `id` column
- Only tested on MySQL