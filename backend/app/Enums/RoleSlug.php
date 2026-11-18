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
    case AdministrationOfficer = 'administration-officer';
    case AdministrationManager = 'administration-manager';
    case InsuranceOfficer = 'insurance-officer';
    case FleetOfficer = 'fleet-officer';
    case SecurityOfficer = 'security-officer';
    case CctvApprover = 'cctv-approver';

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
            self::AdministrationOfficer => 'Administration Officer',
            self::AdministrationManager => 'Administration Manager',
            self::InsuranceOfficer => 'Insurance Officer',
            self::FleetOfficer => 'Fleet Officer',
            self::SecurityOfficer => 'Security Officer',
            self::CctvApprover => 'CCTV Approver',
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
            self::AdministrationOfficer => 40,
            self::InsuranceOfficer => 40,
            self::FleetOfficer => 40,
            self::SecurityOfficer => 40,
            self::CctvApprover => 40,
            self::AdministrationManager => 70,
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

    /**
     * Roles that may reach the Administration help desk staff area.
     *
     * @return array<int, string>
     */
    public static function administrationStaffSlugs(): array
    {
        return [
            ...self::administrationDeskSlugs(),
            self::Administrator->value,
            self::SuperAdmin->value,
        ];
    }

    /**
     * Administration desk roles that can be assigned requests.
     *
     * @return array<int, string>
     */
    public static function administrationDeskSlugs(): array
    {
        return [
            self::AdministrationOfficer->value,
            self::AdministrationManager->value,
            self::InsuranceOfficer->value,
            self::FleetOfficer->value,
            self::SecurityOfficer->value,
            self::CctvApprover->value,
        ];
    }}
