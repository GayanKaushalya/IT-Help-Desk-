<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleSlug;
use App\Exceptions\ApiException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-service employee registration.
 *
 * The Employee role is assigned here, never from the request, so this
 * endpoint cannot be used to create IT staff or administrators.
 */
class RegistrationService
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{full_name: string, branch_id: int, employee_id: string, username: string, password: string}  $data
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function register(array $data, Request $request): array
    {
        $settings = app(SettingService::class)->all();

        if (($settings['allow_self_registration'] ?? '1') !== '1') {
            throw new ApiException(
                'Self-service registration is currently disabled. Contact IT support for an account.',
                Response::HTTP_FORBIDDEN,
                'REGISTRATION_DISABLED',
            );
        }

        $employeeRole = Role::query()->where('slug', RoleSlug::Employee->value)->first();

        if (! $employeeRole) {
            throw new RuntimeException('The employee role has not been seeded.');
        }

        $user = DB::transaction(function () use ($data, $employeeRole) {
            // EPF is no longer collected at registration; keep a unique placeholder
            // so the existing unique column constraint stays satisfied.
            $user = User::query()->create([
                'full_name' => $data['full_name'],
                'username' => $data['username'],
                'employee_id' => $data['employee_id'],
                'epf_number' => 'NA-'.$data['employee_id'],
                'password' => $data['password'],
                'branch_id' => $data['branch_id'],
                'role_id' => $employeeRole->id,
                'is_active' => true,
                'is_approved' => true,
            ]);

            // Lifecycle timestamps stay guarded so a request cannot set them.
            $user->forceFill(['approved_at' => now()])->save();

            return $user;
        });

        $this->audit->log(
            event: 'auth.register',
            subject: $user,
            description: $user->username.' registered an employee account',
        );

        return $this->auth->establishSession($user, remember: false, request: $request);
    }
}
