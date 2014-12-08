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

You should now be able to use the program from the command line (where drt file is stored)
```
php drt
```

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
php drt dedupe tableName columnName
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
./drt dedupe tableName firstname:lastname --backups=false
```

----------------------------

###Remapping####

After you run the `dedupe` command you will have a backup of your table called `yourTableName_with_dupes` It is your original table.

If you passed a no backups flag because you have a backup already, rename your backup to `yourTableName_with_dupes`

This backup table needs to be present for remapping to work. It won't be written to but needs to be read from.

Suppose you have this `teams_with_dupes` table:

id | team
------------- | -------------
2  | Knicks
3  | Knicks
4  | Lakers
5  | Knicks

And the `teams` table (remember, you deduped already)

id | team
------------- | -------------
2  | Knicks
4  | Lakers

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
php drt remap remapTable uniquesTable team --parentKey=id --foreignKey=team_id
```

Like the basic dedupe command, you can also specify multiple columns to define row uniqueness.

```
php drt remap remapTable uniquesTable firstname:lastname:birthday --parentKey=id --foreignKey=employee_id
```

By default the remapTable table will be backed up for you. Disable this by passing a `--nobackups` flag

And here is your result of running the first remap command:


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