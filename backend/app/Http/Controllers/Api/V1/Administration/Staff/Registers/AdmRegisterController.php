<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Administration\Staff\Registers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Shared permission checks for the Administration registers.
 */
abstract class AdmRegisterController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        if (! ($request->user()?->hasPermission('adm.registers.view') ?? false)) {
            throw new AuthorizationException;
        }
    }

    protected function authorizeManage(Request $request): void
    {
        if (! ($request->user()?->hasPermission('adm.registers.manage') ?? false)) {
            throw new AuthorizationException;
        }
    }
}
