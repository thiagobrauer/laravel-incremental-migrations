# Laravel Incremental Migrations

This Laravel package extends the command `php artisan make:migration` to add an incremental number at the end of a migration file, if there is already another migration with the same name. It also has its own artisan command to fix the names of the existing migrations. 

Let's say you created a migration running `php artisan make:migration alter_users_table` and then, sometime later, you need to create another migration to alter users table and you run the same command. Laravel 8 checks this problem before creating a new migration and you will get an `InvalidArgumentException`, so this package may not be useful. In Laravel < 8 versions, when you run `php artisan migrate` you will get an error `Cannot declare class AlterUsersTable, because the name is already in use.`

What this package does is add an incremental number to the end of the migration file, so the classnames will never be the same. If you run `php artisan make:migration alter_users_table` multiple times, you will get `*_alter_users_table.php`, `*_alter_users_table_2.php`, `*_alter_users_table_3.php` and so on.

Laravel 8 now includes an ["squash"](https://laravel.com/docs/8.x/migrations#squashing-migrations ""squash"") feature. Take a look at it, cause it may be an alternative to using this package.
