<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransferTeamOwnershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('transferOwnership', $this->route('team'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');

        return [
            'user' => [
                'required',
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $team->id),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Team $team */
                $team = $this->route('team');
                $targetId = (int) $this->input('user');

                if ($targetId === $this->user()->id) {
                    $validator->errors()->add('user', __('You already own this team.'));

                    return;
                }

                $alreadyOwner = $team->memberships()
                    ->where('user_id', $targetId)
                    ->where('role', TeamRole::Owner)
                    ->exists();

                if ($alreadyOwner) {
                    $validator->errors()->add('user', __('That member is already an owner.'));
                }
            },
        ];
    }
}
