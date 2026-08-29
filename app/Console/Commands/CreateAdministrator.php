<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Fortify\PasswordValidationRules;
use App\AuditAction;
use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('afyascope:create-administrator')]
#[Description('Securely create the initial AfyaScope Administrator account')]
class CreateAdministrator extends Command
{
    use PasswordValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(RecordAuditLog $recordAuditLog): int
    {
        $administratorRole = Role::query()
            ->where('slug', StaffRole::Administrator->value)
            ->first();

        if (! $administratorRole instanceof Role) {
            $this->components->error('The canonical Administrator role is missing. Run the RBAC seeder before bootstrapping an Administrator.');

            return self::FAILURE;
        }

        if ($this->activeAdministratorExists($administratorRole)) {
            $this->components->error('An active Administrator already exists. Create additional Administrators through staff administration.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Name'));
        $email = Str::lower(trim((string) $this->ask('Email address')));
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($administratorRole, $name, $email, $password, $recordAuditLog): User {
                $lockedRole = Role::query()
                    ->whereKey($administratorRole->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->activeAdministratorExists($lockedRole)) {
                    throw new RuntimeException('An active Administrator was created while this command was running.');
                }

                $user = new User;
                $user->role_id = $lockedRole->id;
                $user->is_active = true;
                $user->name = $name;
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->save();

                $recordAuditLog->handle(
                    actor: null,
                    action: AuditAction::AdministratorBootstrapped,
                    subject: $user,
                    afterValues: [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => [
                            'slug' => $lockedRole->slug,
                            'name' => $lockedRole->name,
                        ],
                        'is_active' => $user->is_active,
                    ],
                );

                return $user;
            });
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Administrator account created for {$user->email}.");

        return self::SUCCESS;
    }

    private function activeAdministratorExists(Role $administratorRole): bool
    {
        return User::query()
            ->whereBelongsTo($administratorRole)
            ->where('is_active', true)
            ->exists();
    }
}
