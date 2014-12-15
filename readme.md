MySQL DB Dedupe and Remap Tool
======================

Purpose
------------

- Removes duplicate records from your database and remaps foreign keys in other tables

- You can easily define the uniqueness of a row using one or more columns

- It works well on large tables (10M+ rows) as well.

- Designed to run directly on production tables

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

Copy `config/database.php.sample` to `config/database.php` and fill it out


You should now be able to use the program from the command line (where drt file is stored)
```
php drt
```

Purpose
------------

###Deduplicating Tables####

Suppose you have the following `people` table:

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

**i.e.**

```
php drt dedupe people name
```

Your original table will not be touched, and you will get this table `people_uniques`

id | name
------------- | -------------
2  | Mary
3  | Joseph



You will also get this table `people_removals`

id | name | new_id
------------- | ------------- | -------------
5  | Mary | 2
6  | Joseph | 3
7  | Joseph | 3


----------------------------

But what if you have a table where the uniqueness of defined over three columns? No problem.

id | firstname | lastname | birthday
------------- | ------------- | ------------- | -------------
2  | Mary  |  Smith | 1991-01-01
3  | Joseph  |  Parker | 1984-02-02
5  | Mary  |  Kate | 1981-08-08
6  | mary  |  kate | 2001-03-03
7  | Joseph  |  Parker | 1984-02-02


Seperate the columns with a `:` in the second argument:

```
php drt dedupe people firstname:lastname:birthday
```

You will get a new table `people_uniques`

id | firstname | lastname | birthday
------------- | ------------- | ------------- | -------------
2  | Mary  |  Smith | 1991-01-01
3  | Joseph  |  Parker | 1984-02-02
5  | Mary  |  Kate | 1981-08-08
6  | mary  |  kate | 2001-03-03

And another table `people_removals`

id | firstname | lastname | birthday | new_id
------------- | ------------- | ------------- | -------------
7 | Joseph | Parker | 1984-02-02 | 3


----------------------------

You can continue to deduplicate on different columns.

Your `uniques` table will get smaller, and `your` removals table will get bigger.

Take another look at the last stage our tables were in.

Let's keep deduplicating further on new rules...

```
php drt dedupe tableName firstname:lastname
```

Now `people_uniques` is like this:

id | firstname | lastname | birthday
------------- | ------------- | ------------- | -------------
2  | Mary  |  Smith | 1991-01-01
3  | Joseph  |  Parker | 1984-02-02
5  | Mary  |  Kate | 1981-08-08


And `people_removals` is like this:

id | firstname | lastname | birthday | new_id
------------- | ------------- | ------------- | ------------- | -------------
7  | Joseph  |  Parker | 1984-02-02 | 3
6  | mary  |  kate | 2001-03-03 | 5


###Remapping####

After you run `dedupe` you will have **table_uniques** and **table_removals**, as well as your original table.

The removals table **needs to be present** for remapping to work. 

It won't be written to but **needs to be read from**.

Suppose you have this `teams` table:

id | team
------------- | -------------
2  | Knicks
3  | Knicks
4  | Lakers
5  | Knicks

And the `teams_uniques` table (remember, you deduped already)

id | team | 
------------- | -------------
2  | Knicks
4  | Lakers

And you also have this `teams_removals` table which is used for remapping:

id | year | new_id | 
------------- | ------------- | -------------
3  | 2006 | 2
5  | 2007 | 4

You can now remap the foreign keys on other tables pointing to `teams.id`

```
php drt remap remapTable removalsTable foreignKey
```

**i.e.**
```
php drt remap champions teams_removals team_id
```

You should backup your remap table prior to running the `remap` command.

If your remapping doesn't finish the first time, just run it again. It won't hurt anything.


###Swapping in the new table###

Going back to the `people` table example...

Finish remapping foreign keys for all tables that point to `people.id`

Now for the final coup-de-grace!

```sql
RENAME TABLE table TO table_bak;
RENAME TABLE table_uniques TO table;
DROP TABLE table_bak -- optional
```

Congrats! You've deduped and remapped your table.


Contribution Guidelines
------------

- Post an issue!
- Fork and pull.

Notes
------------

- For the time being, your original table with duplicates must have an `id` column
- Only works on MySQL, but I'm open to adding more support