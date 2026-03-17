<?php

namespace App\Rules;

use DB;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TokenPolicy implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {    
        if (strlen($value) != 56) {
            $fail(__('validation.the_policy_ID_must_be_56hex_chars'));
            return;
        }
                
        if (!preg_match('/^[0-9a-fA-F]+$/', $value)) {
            $fail(__('validation.the_policy_ID_contains_invalid_characters'));
            return;
        }
        
        $policy = DB::connection('cexplorer')->select('SELECT EXISTS (SELECT 1 FROM multi_asset ma WHERE ma.policy = \'\x'.$value.'\') AS policy_exists');
        
        if ($policy[0]->policy_exists === false) {
            $fail(__('validation.the_policy_ID_does_not_exist'));
        }
    }

}
