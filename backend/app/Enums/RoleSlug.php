<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The system roles seeded by RoleSeeder.
 *
 * Referencing these instead of raw strings keeps authorization checks
 * refactor-safe across controllers, policies and middleware.
 */
enum RoleSlug: string
{
    case Employee = 'employee';
    case Technician = 'technician';
    case Supervisor = 'supervisor';
    case Administrator = 'administrator';
    case SuperAdmin = 'super-admin';
    case HrOfficer = 'hr-officer';
    case HrManager = 'hr-manager';
    case FinanceOfficer = 'finance-officer';
    case ConfidentialComplaintHandler = 'confidential-complaint-handler';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Technician => 'IT Technician',
            self::Supervisor => 'IT Supervisor',
            self::Administrator => 'Administrator',
            self::SuperAdmin => 'Super Admin',
            self::HrOfficer => 'HR Officer',
            self::HrManager => 'HR Manager',
            self::FinanceOfficer => 'Finance Officer',
            self::ConfidentialComplaintHandler => 'Confidential Complaint Handler',
        };
    }

    /**
     * Privilege weight used for coarse comparisons between roles.
     */
    public function level(): int
    {
        return match ($this) {
            self::Employee => 10,
            self::FinanceOfficer => 25,
            self::Technician => 30,
            self::ConfidentialComplaintHandler => 35,
            self::HrOfficer => 40,
            self::Supervisor => 50,
            self::HrManager => 70,
            self::Administrator => 80,
            self::SuperAdmin => 100,
        };
    }

    /**
     * Roles that may reach the IT administration area.
     *
     * @return array<int, string>
     */
    public static function staffSlugs(): array
    {
        return [
            self::Technician->value,
            self::Supervisor->value,
            self::Administrator->value,
            self::SuperAdmin->value,
        ];
    }

    /**
     * Roles that may reach the HR administration area.
     *
     * @return array<int, string>
     */
    public static function hrStaffSlugs(): array
    {
        return [
            self::HrOfficer->value,
            self::HrManager->value,
            self::ConfidentialComplaintHandler->value,
            self::Administrator->value,
            self::SuperAdmin->value,
        ];
    }

    /**
     * Roles that may work Finance referrals from HR.
     *
     * @return array<int, string>
     */
    public static function financeReferralSlugs(): array
    {
        return [
            self::FinanceOfficer->value,
            self::HrManager->value,
            self::Administrator->value,
            self::SuperAdmin->value,
        ];
    }
}
