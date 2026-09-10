<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * The admin panel (resources/views/admin/*) is plain server-rendered Blade
 * with native <dialog> modals -- a create/update form posts, and on success
 * or failure the whole page reloads (no fetch/AJAX). A page can have several
 * instances of the *same* modal at once (one "Update Company" dialog per row
 * in the company table, one "Update User" dialog per row, etc.), all built
 * from the same Blade @foreach block and so all sharing the same field
 * names ("name", "email", ...).
 *
 * Laravel's default failedValidation() puts every error under one bag
 * ('default'). If Company #5's update fails, $errors->has('email') is true
 * globally -- so @error('email') inside the *unrelated* modal for Company
 * #9, sitting right there in the same page's markup, would show that same
 * stray error the next time someone opens it, until the next full
 * navigation clears $errors.
 *
 * Each of these forms already carries a hidden _modal_id input (its own
 * dialog's DOM id, e.g. "update-company-5") so the shared admin layout
 * script knows which dialog to reopen after a failed submit (see
 * layout.blade.php). Reusing that same value as the *error bag* name keeps
 * validation errors scoped to that one specific dialog instance -- the
 * Blade side just needs to check @error('field', $modalId) with the
 * matching id instead of the unnamed default bag.
 */
trait ScopesErrorsToModal
{
    protected function failedValidation(Validator $validator)
    {
        $modalId = $this->input('_modal_id');

        if ($modalId) {
            $this->errorBag = $modalId;
        }

        parent::failedValidation($validator);
    }
}
