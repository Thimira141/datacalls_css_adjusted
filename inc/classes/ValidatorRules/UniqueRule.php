<?php
namespace inc\classes\ValidatorRules;

/**
 * Custom validation rule to check if a value is unique in a database table.
 * This rule can be used to validate form inputs against existing records.
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

require_once __DIR__ . '/../../../config.php';
use Rakit\Validation\Rule;
use Illuminate\Database\Capsule\Manager as DB;

class UniqueRule extends Rule
{
    protected $message = ":attribute is already taken";
    protected $fillableParams = ['table', 'column', 'except', 'except_column'];

    public function check($value): bool
    {
        $table = $this->parameter('table');
        $column = $this->parameter('column');
        // Parse "table,column,except|ignore" style input
        $except = $this->parameter('except');
        $except_column = $this->parameter('except_column');

        // Support "table,column,except|ignore" in $except
        if (is_string($except) && strpos($except, '|') !== false) {
            [$except, $ignore] = explode('|', $except, 2);
            // Optionally, you can use $ignore for further logic if needed
        }

        if ($except_column === null) {
            $except_column = $column;
        }
        // query
        $query = DB::table($table)->where($column, '=', $value);
        if ($except !== null) {
            $query->where($except_column, '!=', $except);
        }
        $count = $query->count();

        return $count == 0;
    }
}