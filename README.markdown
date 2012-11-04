# Fitzgerald needs a new home!

I haven't worked with PHP in over 2 years at this point, and this
library, as small as it is, deserves more attention from its maintainer.

If you're interested in taking over ownership of this framework, let me know.
Thanks!

##  Fitzgerald is a tiny PHP framework that was inspired oh so heavily by the wondrous Sinatra of the Ruby world.

See example.php for a look at how to use Fitzgerald, and read the blog posts: 
    - http://autonomousmachine.com/2008/11/21/fitzgerald-a-sinatra-clone-in-php
    - http://autonomousmachine.com/2009/2/3/fitzgerald-update-before-filters-and-senddownload

Have fun!

Getting started
===============

Copy the lib folder from the repo to your working directory. Create a file for your application for instance app.php. Inside this file include fitzgerald and subclass it:
```php
include('lib/fitzgerald.php');

class MyApplication extends Fitzgerald {
}
```
Create an index.php in you DOCUMENT_ROOT and include your app:
```php
include('../app.php');
```
Create a .htaccess file with the following contents:
```
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```
In your app.php file create an instance of the subclacc you made. Add an action to the class and setup a route, than call the run() method of fitzgerald:
```php
include('lib/fitzgerald.php');

class MyApplication extends Fitzgerald {
    public function get_index() {
        return $this->render('index');
    }
}
$app = new MyApplication(array('layout' => 'mylayout'));
// index action
$app->get('/', 'get_index');

$app->run();
```
Create a layout and a view in the views folder and open the domain in a browser. You should see the contents of the index view.

To pass data to a view you need to pass it to the render function:
```php
class MyApplication extends Fitzgerald {
    public function get_index() {
        return $this->render('index', array('data' => 'my test data'));
    }
}
```
