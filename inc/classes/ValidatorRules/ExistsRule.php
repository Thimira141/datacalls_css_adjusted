<?php
namespace inc\classes\ValidatorRules;

/**
 * Custom validation rule to check if a value exists in a database table.
 * This rule can be used to validate form inputs against existing records.
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

require_once __DIR__ . '/../../../config.php';
use Rakit\Validation\Rule;
use Illuminate\Database\Capsule\Manager as DB;

class ExistsRule extends Rule
{
    protected $message = ":attribute does not exist";
    protected $fillableParams = ['table', 'column'];

    /**
     * Check if the value exists in the specified table and column.
     * This method will query the database to see if the value exists.
     * @param mixed $value
     * @return bool
     * @author Thimira Dilshan <thimirad865@gmail.com>
     */
    public function check($value): bool
    {
        $table = $this->parameter('table');
        $column = $this->parameter('column');
        // query
        $count = DB::table($table)->where($column, $value)->count();
        return $count > 0;
    }
}