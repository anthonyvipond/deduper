Laravel Deduper
======================

Purpose
------------

A custom Artisan command removes duplicate records from your MySQL database table.

You can easily define the uniqueness of a row using one or more columns.

It works well on large tables (10M+ rows) as well.

Installation
------------

The recommended way is through [composer](http://getcomposer.org).

Edit `composer.json` and add:

```json
{
    "require": {
        "anthonyvipond/deduper-laravel": "dev-master"
    }
}
```

And install dependencies:

```bash
    composer install
```

Find the `providers` key in `app/config/app.php` and register the **Deduper Service Provider**.

```php
'providers' => array(
    // ...

    'Vipond\Deduper\DeduperServiceProvider',
)
```

You should now see the command
```
php artisan list
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
php artisan deduper:dedupe tableName name
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
php artisan deduper:dedupe tableName firstname:lastname
```

You will get this:

id | firstname | lastname
------------- | ------------- | -------------
2  | Mary  |  Smith
3  | Joseph  |  Parker
5  | Mary  |  Kate

----------------------------

Already have your table backed up? You can pass a **no backup** flag. It's true by default.

```
php artisan deduper:dedupe tableName name --backup=false
```

If you don't want to see SQL queries being run, pass an --sql flag. It's true by default.
```
php artisan deduper:dedupe tableName name --backup=false --sql=false
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
php artisan deduper:remap remapTable duplicatesTable team --duplicatesColumn=id --remapFkColumn=team_id
```

Like the basic dedupe command, you can also specify multiple columns to define row uniqueness.

```
php artisan deduper:remap remapTable duplicatesTable firstname:lastname:birthday --duplicatesColumn=id --remapFkColumn=employee_id
```

By default both tables will be backed up for you. Disable this by passing a `--backup=false` flag

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

Notes
------------

- As of right now, to use `deduper:remap` both duplicates and remap table must have an `id` column
- Only tested on MySQL, feel free to make pull requests so it works on other DBMS'