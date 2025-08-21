<?php
namespace inc\classes\ValidatorRules;

/**
 * Custom validation rule for 'string' data type
 * Usage: 'field' => 'string'
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

require_once __DIR__ . '/../../../config.php';
use Rakit\Validation\Rule;

class StringRule extends Rule
{
    protected $message = ":attribute is not a string type data";
    public function check($value): bool
    {
        // Accept only if value is a string and not an array/object/null
        return is_string($value);
    }

}