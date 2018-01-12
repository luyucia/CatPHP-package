# CatPHP-package
CatPHP composer version

```
use CatPHP\DB\DB;
use CatPHP\DB\Sql;

$dbconfig    = [
    'type'=>'mysql',
    'host'=>'xxxxxxx',
    'username'=>'root',
    'password'=>'xxxxxxxxx',
    'database'=>'xxxxx',
    'port'=>' 3306',
    'charset'=>'utf8'
];


$db          = new DB($dbconfig);

$rs = $db->page(1,2)->query("select * from m_user");

```
