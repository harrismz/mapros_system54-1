## MAPROS

## Description


## Installation

1. run `composer intsall`
2. setup your `.env` file
3. run `php artisan migrate`
4. run `php artisan voyager:install`
5. run `php artisan make:auth`
6. find another laravel installer and copy `another-laravel-app\app\Http\Controllers\Auth` into `this-folder\app\Http\Controllers\Auth` (it is because somehow, php artisan make:auth doesn't gone right on this repo ) --> studpid right ??
7. find `C:\xampp\htdocs\mapros_system54\vendor\tcg\voyager\migrations\2017_11_26_013050_add_user_role_relationship.php` and delete `$table->integer('role_id')->change();` line. because it causing us cannot rollback our migration ( it is due to vendor bug )
8. you can start make a user
9. run `php artisan voyager:admin youremail@email.com` to get admin access.
10. acctually this is private project,

## NOTES
1. Don't forget to run `php artisan voyager:install` everytime you run `php artisan migrate:refresh`.
and do step 7 again also. 

2. run `php artisan voyager:save` to save your progress in voyager;

3. you need to specify your env `APP_URL` into sub dir if develop using xampp. example: `APP_URL=http://localhost/mapros_system54`

## Test
run `vendor\bin\phpunit` to run test on this parent directory;
don't forget to changes `<env name="DB_DATABASE" value="your-test-db"/>` on phpunit.xml to refer to your db 

