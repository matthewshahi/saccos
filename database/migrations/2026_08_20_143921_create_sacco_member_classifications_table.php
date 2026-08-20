<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the master catalogue of SACCO member classifications / roles.
     *
     * IMPORTANT:
     *
     * sacco_members.member_position = 2 remains unchanged.
     *
     * member_position = 2 continues to broadly mean:
     * SACCO official / staff.
     *
     * This table stores detailed roles.
     *
     * Roles are INDEPENDENT.
     *
     * A member may eventually hold many roles at the same time, e.g.:
     *
     * - Board Director
     * - Credit Committee Chairperson
     * - Education Committee Member
     * - Investment Committee Member
     *
     * classification_category is ONLY for grouping/filtering/display.
     * It does not create an exclusive membership relationship.
     */
    public function up(): void
    {
        Schema::create('sacco_member_classifications', function (Blueprint $table) {
            $table->increments('classification_id');

            /*
             * Human-readable role.
             */
            $table->string('classification_name', 150);

            /*
             * Stable unique application code.
             */
            $table->string('classification_code', 120)
                ->unique();

            /*
             * Used ONLY for organization, filtering and presentation.
             *
             * Examples:
             * BOARD
             * SUPERVISORY_COMMITTEE
             * CREDIT_COMMITTEE
             * EDUCATION_COMMITTEE
             * FINANCE
             * CREDIT
             * ICT
             */
            $table->string('classification_category', 120)
                ->nullable();

            /*
             * Optional explanation of the role.
             */
            $table->text('classification_description')
                ->nullable();

            /*
             * Whether the role is available for assignment.
             *
             * Y = Active
             * N = Inactive
             */
            $table->char('classification_active', 1)
                ->default('Y');

            /*
             * Controls dropdown/list display order.
             */
            $table->unsignedInteger('classification_sort_order')
                ->default(0);

            /*
             * User who created/modified the classification
             * where applicable.
             */
            $table->integer('classification_user_id')
                ->nullable();

            $table->timestamp('classification_transdate')
                ->useCurrent();

            /*
             * Indexes.
             */
            $table->index('classification_name');
            $table->index('classification_category');
            $table->index('classification_active');
            $table->index('classification_sort_order');
        });

        /*
         * ============================================================
         * DEFAULT SACCO CLASSIFICATIONS
         * ============================================================
         */

        $roles = [];
        $sortOrder = 10;

        /*
         * Add one independent role.
         */
        $addRole = static function (
            string $name,
            string $code,
            string $category,
            ?string $description = null
        ) use (&$roles, &$sortOrder): void {
            $roles[] = [
                'classification_name' => $name,
                'classification_code' => $code,
                'classification_category' => $category,
                'classification_description' => $description,
                'classification_active' => 'Y',
                'classification_sort_order' => $sortOrder,
                'classification_user_id' => null,
            ];

            $sortOrder += 10;
        };

        /*
         * Add the standard positions within a committee.
         *
         * These remain independent roles.
         */
        $addCommittee = static function (
            string $committeeName,
            string $code,
            string $category
        ) use (&$roles, &$sortOrder): void {
            $positions = [
                'CHAIRPERSON' => 'Chairperson',
                'VICE_CHAIRPERSON' => 'Vice Chairperson',
                'SECRETARY' => 'Secretary',
                'MEMBER' => 'Member',
            ];

            foreach ($positions as $positionCode => $positionName) {
                $roles[] = [
                    'classification_name' =>
                        $committeeName . ' ' . $positionName,

                    'classification_code' =>
                        $code . '_' . $positionCode,

                    'classification_category' => $category,

                    'classification_description' =>
                        $positionName . ' of the ' . $committeeName . '.',

                    'classification_active' => 'Y',

                    'classification_sort_order' => $sortOrder,

                    'classification_user_id' => null,
                ];

                $sortOrder += 10;
            }
        };

        /*
         * ============================================================
         * 1. BOARD OF DIRECTORS
         * ============================================================
         */

        $addRole(
            'Board Chairperson',
            'BOARD_CHAIRPERSON',
            'BOARD',
            'Chairperson of the SACCO Board of Directors.'
        );

        $addRole(
            'Board Vice Chairperson',
            'BOARD_VICE_CHAIRPERSON',
            'BOARD',
            'Vice Chairperson of the SACCO Board of Directors.'
        );

        $addRole(
            'Board Honorary Secretary',
            'BOARD_HONORARY_SECRETARY',
            'BOARD',
            'Honorary Secretary of the SACCO Board.'
        );

        $addRole(
            'Board Treasurer',
            'BOARD_TREASURER',
            'BOARD',
            'Board Treasurer where this office exists under the SACCO by-laws.'
        );

        $addRole(
            'Board Director',
            'BOARD_DIRECTOR',
            'BOARD',
            'Elected member of the SACCO Board of Directors.'
        );

        /*
         * ============================================================
         * 2. SUPERVISORY COMMITTEE
         * ============================================================
         */

        $addRole(
            'Supervisory Committee Chairperson',
            'SUPERVISORY_COMMITTEE_CHAIRPERSON',
            'SUPERVISORY_COMMITTEE',
            'Chairperson of the SACCO Supervisory Committee.'
        );

        $addRole(
            'Supervisory Committee Vice Chairperson',
            'SUPERVISORY_COMMITTEE_VICE_CHAIRPERSON',
            'SUPERVISORY_COMMITTEE',
            'Vice Chairperson of the SACCO Supervisory Committee where applicable.'
        );

        $addRole(
            'Supervisory Committee Secretary',
            'SUPERVISORY_COMMITTEE_SECRETARY',
            'SUPERVISORY_COMMITTEE',
            'Secretary of the SACCO Supervisory Committee.'
        );

        $addRole(
            'Supervisory Committee Member',
            'SUPERVISORY_COMMITTEE_MEMBER',
            'SUPERVISORY_COMMITTEE',
            'Member of the SACCO Supervisory Committee.'
        );

        /*
         * ============================================================
         * 3. CREDIT COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Credit Committee',
            'CREDIT_COMMITTEE',
            'CREDIT_COMMITTEE'
        );

        /*
         * ============================================================
         * 4. AUDIT AND RISK COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Audit and Risk Committee',
            'AUDIT_RISK_COMMITTEE',
            'AUDIT_RISK_COMMITTEE'
        );

        /*
         * ============================================================
         * 5. FINANCE AND ADMINISTRATION COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Finance and Administration Committee',
            'FINANCE_ADMIN_COMMITTEE',
            'FINANCE_ADMIN_COMMITTEE'
        );

        /*
         * ============================================================
         * 6. EDUCATION COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Education Committee',
            'EDUCATION_COMMITTEE',
            'EDUCATION_COMMITTEE'
        );

        /*
         * ============================================================
         * 7. EDUCATION AND TRAINING COMMITTEE
         *
         * Some SACCOs use this expanded name instead of simply
         * Education Committee.
         * ============================================================
         */

        $addCommittee(
            'Education and Training Committee',
            'EDUCATION_TRAINING_COMMITTEE',
            'EDUCATION_TRAINING_COMMITTEE'
        );

        /*
         * ============================================================
         * 8. INVESTMENT COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Investment Committee',
            'INVESTMENT_COMMITTEE',
            'INVESTMENT_COMMITTEE'
        );

        /*
         * ============================================================
         * 9. HUMAN RESOURCES / NOMINATIONS COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Human Resources Committee',
            'HR_COMMITTEE',
            'HR_COMMITTEE'
        );

        $addCommittee(
            'Human Resources and Nominations Committee',
            'HR_NOMINATIONS_COMMITTEE',
            'HR_NOMINATIONS_COMMITTEE'
        );

        /*
         * ============================================================
         * 10. ICT / TECHNOLOGY COMMITTEES
         * ============================================================
         */

        $addCommittee(
            'ICT Committee',
            'ICT_COMMITTEE',
            'ICT_COMMITTEE'
        );

        $addCommittee(
            'ICT and Innovation Committee',
            'ICT_INNOVATION_COMMITTEE',
            'ICT_INNOVATION_COMMITTEE'
        );

        /*
         * ============================================================
         * 11. STRATEGY COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Strategy Committee',
            'STRATEGY_COMMITTEE',
            'STRATEGY_COMMITTEE'
        );

        $addCommittee(
            'Strategy and Business Development Committee',
            'STRATEGY_BUSINESS_DEVELOPMENT_COMMITTEE',
            'STRATEGY_BUSINESS_DEVELOPMENT_COMMITTEE'
        );

        /*
         * ============================================================
         * 12. MEMBERSHIP / WELFARE COMMITTEES
         * ============================================================
         */

        $addCommittee(
            'Membership Committee',
            'MEMBERSHIP_COMMITTEE',
            'MEMBERSHIP_COMMITTEE'
        );

        $addCommittee(
            'Member Welfare Committee',
            'MEMBER_WELFARE_COMMITTEE',
            'MEMBER_WELFARE_COMMITTEE'
        );

        /*
         * ============================================================
         * 13. MARKETING / BUSINESS DEVELOPMENT COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Marketing and Business Development Committee',
            'MARKETING_BUSINESS_DEVELOPMENT_COMMITTEE',
            'MARKETING_BUSINESS_DEVELOPMENT_COMMITTEE'
        );

        /*
         * ============================================================
         * 14. GOVERNANCE / ETHICS COMMITTEES
         * ============================================================
         */

        $addCommittee(
            'Governance Committee',
            'GOVERNANCE_COMMITTEE',
            'GOVERNANCE_COMMITTEE'
        );

        $addCommittee(
            'Ethics and Governance Committee',
            'ETHICS_GOVERNANCE_COMMITTEE',
            'ETHICS_GOVERNANCE_COMMITTEE'
        );

        /*
         * ============================================================
         * 15. NOMINATION / VETTING COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Nomination and Vetting Committee',
            'NOMINATION_VETTING_COMMITTEE',
            'NOMINATION_VETTING_COMMITTEE'
        );

        /*
         * ============================================================
         * 16. PROCUREMENT COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Procurement Committee',
            'PROCUREMENT_COMMITTEE',
            'PROCUREMENT_COMMITTEE'
        );

        /*
         * ============================================================
         * 17. TENDER / EVALUATION COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Tender Committee',
            'TENDER_COMMITTEE',
            'TENDER_COMMITTEE'
        );

        $addCommittee(
            'Tender Evaluation Committee',
            'TENDER_EVALUATION_COMMITTEE',
            'TENDER_EVALUATION_COMMITTEE'
        );

        /*
         * ============================================================
         * 18. DEBT RECOVERY COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Debt Recovery Committee',
            'DEBT_RECOVERY_COMMITTEE',
            'DEBT_RECOVERY_COMMITTEE'
        );

        /*
         * ============================================================
         * 19. RISK MANAGEMENT COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Risk Management Committee',
            'RISK_MANAGEMENT_COMMITTEE',
            'RISK_MANAGEMENT_COMMITTEE'
        );

        /*
         * ============================================================
         * 20. ASSET AND LIABILITY COMMITTEE - ALCO
         * ============================================================
         */

        $addCommittee(
            'Asset and Liability Committee',
            'ALCO',
            'ASSET_LIABILITY_COMMITTEE'
        );

        /*
         * ============================================================
         * 21. DISCIPLINARY COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Disciplinary Committee',
            'DISCIPLINARY_COMMITTEE',
            'DISCIPLINARY_COMMITTEE'
        );

        /*
         * ============================================================
         * 22. EXECUTIVE COMMITTEE
         * ============================================================
         */

        $addCommittee(
            'Executive Committee',
            'EXECUTIVE_COMMITTEE',
            'EXECUTIVE_COMMITTEE'
        );

        /*
         * ============================================================
         * 23. DELEGATES / REPRESENTATIVES
         * ============================================================
         */

        $addRole(
            'Chief Delegate',
            'CHIEF_DELEGATE',
            'DELEGATES',
            'Chief or principal delegate where the SACCO uses a delegate system.'
        );

        $addRole(
            'Delegate Chairperson',
            'DELEGATE_CHAIRPERSON',
            'DELEGATES',
            'Chairperson of the SACCO delegate structure.'
        );

        $addRole(
            'Delegate Vice Chairperson',
            'DELEGATE_VICE_CHAIRPERSON',
            'DELEGATES',
            'Vice Chairperson of the SACCO delegate structure.'
        );

        $addRole(
            'Delegate Secretary',
            'DELEGATE_SECRETARY',
            'DELEGATES',
            'Secretary of the SACCO delegate structure.'
        );

        $addRole(
            'Delegate',
            'DELEGATE',
            'DELEGATES',
            'Elected SACCO delegate.'
        );

        $addRole(
            'Zone Delegate',
            'ZONE_DELEGATE',
            'DELEGATES',
            'Delegate representing a defined geographical or administrative zone.'
        );

        $addRole(
            'Branch Delegate',
            'BRANCH_DELEGATE',
            'DELEGATES',
            'Delegate representing a SACCO branch.'
        );

        $addRole(
            'Regional Delegate',
            'REGIONAL_DELEGATE',
            'DELEGATES',
            'Delegate representing a SACCO region.'
        );

        /*
         * ============================================================
         * 24. EXECUTIVE MANAGEMENT
         * ============================================================
         */

        $addRole(
            'Chief Executive Officer',
            'CEO',
            'EXECUTIVE_MANAGEMENT',
            'Chief Executive Officer responsible for day-to-day management of the SACCO.'
        );

        $addRole(
            'General Manager',
            'GENERAL_MANAGER',
            'EXECUTIVE_MANAGEMENT',
            'General Manager of the SACCO.'
        );

        $addRole(
            'Deputy Chief Executive Officer',
            'DEPUTY_CEO',
            'EXECUTIVE_MANAGEMENT',
            'Deputy Chief Executive Officer.'
        );

        $addRole(
            'Deputy General Manager',
            'DEPUTY_GENERAL_MANAGER',
            'EXECUTIVE_MANAGEMENT',
            'Deputy General Manager.'
        );

        $addRole(
            'Chief Operations Officer',
            'COO',
            'EXECUTIVE_MANAGEMENT',
            'Chief Operations Officer.'
        );

        $addRole(
            'Chief Finance Officer',
            'CFO',
            'EXECUTIVE_MANAGEMENT',
            'Chief Finance Officer.'
        );

        $addRole(
            'Chief Risk Officer',
            'CRO',
            'EXECUTIVE_MANAGEMENT',
            'Chief Risk Officer.'
        );

        $addRole(
            'Chief Information Officer',
            'CIO',
            'EXECUTIVE_MANAGEMENT',
            'Chief Information Officer.'
        );

        /*
         * ============================================================
         * 25. FINANCE / ACCOUNTS / TREASURY
         * ============================================================
         */

        $addRole(
            'Head of Finance',
            'HEAD_OF_FINANCE',
            'FINANCE'
        );

        $addRole(
            'Finance Manager',
            'FINANCE_MANAGER',
            'FINANCE'
        );

        $addRole(
            'Finance Officer',
            'FINANCE_OFFICER',
            'FINANCE'
        );

        $addRole(
            'Senior Accountant',
            'SENIOR_ACCOUNTANT',
            'FINANCE'
        );

        $addRole(
            'Accountant',
            'ACCOUNTANT',
            'FINANCE'
        );

        $addRole(
            'Assistant Accountant',
            'ASSISTANT_ACCOUNTANT',
            'FINANCE'
        );

        $addRole(
            'Accounts Officer',
            'ACCOUNTS_OFFICER',
            'FINANCE'
        );

        $addRole(
            'Accounts Assistant',
            'ACCOUNTS_ASSISTANT',
            'FINANCE'
        );

        $addRole(
            'Treasury Manager',
            'TREASURY_MANAGER',
            'FINANCE'
        );

        $addRole(
            'Treasury Officer',
            'TREASURY_OFFICER',
            'FINANCE'
        );

        $addRole(
            'Payroll Officer',
            'PAYROLL_OFFICER',
            'FINANCE'
        );

        /*
         * ============================================================
         * 26. CREDIT / LOANS / RECOVERIES
         * ============================================================
         */

        $addRole(
            'Head of Credit',
            'HEAD_OF_CREDIT',
            'CREDIT'
        );

        $addRole(
            'Credit Manager',
            'CREDIT_MANAGER',
            'CREDIT'
        );

        $addRole(
            'Loans Manager',
            'LOANS_MANAGER',
            'CREDIT'
        );

        $addRole(
            'Senior Credit Officer',
            'SENIOR_CREDIT_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Credit Officer',
            'CREDIT_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Loans Officer',
            'LOANS_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Credit Analyst',
            'CREDIT_ANALYST',
            'CREDIT'
        );

        $addRole(
            'Loan Appraisal Officer',
            'LOAN_APPRAISAL_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Credit Administration Officer',
            'CREDIT_ADMINISTRATION_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Recoveries Manager',
            'RECOVERIES_MANAGER',
            'CREDIT'
        );

        $addRole(
            'Debt Recovery Officer',
            'DEBT_RECOVERY_OFFICER',
            'CREDIT'
        );

        $addRole(
            'Collections Officer',
            'COLLECTIONS_OFFICER',
            'CREDIT'
        );

        /*
         * ============================================================
         * 27. OPERATIONS
         * ============================================================
         */

        $addRole(
            'Head of Operations',
            'HEAD_OF_OPERATIONS',
            'OPERATIONS'
        );

        $addRole(
            'Operations Manager',
            'OPERATIONS_MANAGER',
            'OPERATIONS'
        );

        $addRole(
            'Operations Officer',
            'OPERATIONS_OFFICER',
            'OPERATIONS'
        );

        $addRole(
            'Branch Manager',
            'BRANCH_MANAGER',
            'OPERATIONS'
        );

        $addRole(
            'Assistant Branch Manager',
            'ASSISTANT_BRANCH_MANAGER',
            'OPERATIONS'
        );

        $addRole(
            'Branch Operations Officer',
            'BRANCH_OPERATIONS_OFFICER',
            'OPERATIONS'
        );

        /*
         * ============================================================
         * 28. FOSA / BANKING OPERATIONS
         * ============================================================
         */

        $addRole(
            'FOSA Manager',
            'FOSA_MANAGER',
            'FOSA'
        );

        $addRole(
            'FOSA Officer',
            'FOSA_OFFICER',
            'FOSA'
        );

        $addRole(
            'Head Teller',
            'HEAD_TELLER',
            'FOSA'
        );

        $addRole(
            'Teller',
            'TELLER',
            'FOSA'
        );

        $addRole(
            'Cashier',
            'CASHIER',
            'FOSA'
        );

        $addRole(
            'Back Office Officer',
            'BACK_OFFICE_OFFICER',
            'FOSA'
        );

        $addRole(
            'Clearing Officer',
            'CLEARING_OFFICER',
            'FOSA'
        );

        $addRole(
            'Mobile Banking Officer',
            'MOBILE_BANKING_OFFICER',
            'FOSA'
        );

        $addRole(
            'Agency Banking Officer',
            'AGENCY_BANKING_OFFICER',
            'FOSA'
        );

        $addRole(
            'Digital Banking Officer',
            'DIGITAL_BANKING_OFFICER',
            'FOSA'
        );

        /*
         * ============================================================
         * 29. MEMBER SERVICES / CUSTOMER EXPERIENCE
         * ============================================================
         */

        $addRole(
            'Head of Member Services',
            'HEAD_OF_MEMBER_SERVICES',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Member Services Manager',
            'MEMBER_SERVICES_MANAGER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Membership Officer',
            'MEMBERSHIP_OFFICER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Member Services Officer',
            'MEMBER_SERVICES_OFFICER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Customer Service Manager',
            'CUSTOMER_SERVICE_MANAGER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Customer Service Officer',
            'CUSTOMER_SERVICE_OFFICER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Relationship Manager',
            'RELATIONSHIP_MANAGER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Relationship Officer',
            'RELATIONSHIP_OFFICER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Front Office Officer',
            'FRONT_OFFICE_OFFICER',
            'MEMBER_SERVICES'
        );

        $addRole(
            'Complaints Handling Officer',
            'COMPLAINTS_HANDLING_OFFICER',
            'MEMBER_SERVICES'
        );

        /*
         * ============================================================
         * 30. ICT / INFORMATION SECURITY / DIGITAL
         * ============================================================
         */

        $addRole(
            'Head of ICT',
            'HEAD_OF_ICT',
            'ICT'
        );

        $addRole(
            'ICT Manager',
            'ICT_MANAGER',
            'ICT'
        );

        $addRole(
            'ICT Officer',
            'ICT_OFFICER',
            'ICT'
        );

        $addRole(
            'Systems Administrator',
            'SYSTEMS_ADMINISTRATOR',
            'ICT'
        );

        $addRole(
            'Database Administrator',
            'DATABASE_ADMINISTRATOR',
            'ICT'
        );

        $addRole(
            'Network Administrator',
            'NETWORK_ADMINISTRATOR',
            'ICT'
        );

        $addRole(
            'ICT Support Officer',
            'ICT_SUPPORT_OFFICER',
            'ICT'
        );

        $addRole(
            'Information Security Officer',
            'INFORMATION_SECURITY_OFFICER',
            'ICT'
        );

        $addRole(
            'Cyber Security Officer',
            'CYBER_SECURITY_OFFICER',
            'ICT'
        );

        $addRole(
            'Applications Officer',
            'APPLICATIONS_OFFICER',
            'ICT'
        );

        $addRole(
            'Digital Channels Officer',
            'DIGITAL_CHANNELS_OFFICER',
            'ICT'
        );

        /*
         * ============================================================
         * 31. INTERNAL AUDIT
         * ============================================================
         */

        $addRole(
            'Head of Internal Audit',
            'HEAD_OF_INTERNAL_AUDIT',
            'INTERNAL_AUDIT'
        );

        $addRole(
            'Internal Audit Manager',
            'INTERNAL_AUDIT_MANAGER',
            'INTERNAL_AUDIT'
        );

        $addRole(
            'Senior Internal Auditor',
            'SENIOR_INTERNAL_AUDITOR',
            'INTERNAL_AUDIT'
        );

        $addRole(
            'Internal Auditor',
            'INTERNAL_AUDITOR',
            'INTERNAL_AUDIT'
        );

        $addRole(
            'Audit Officer',
            'AUDIT_OFFICER',
            'INTERNAL_AUDIT'
        );

        /*
         * ============================================================
         * 32. RISK / COMPLIANCE / AML
         * ============================================================
         */

        $addRole(
            'Head of Risk',
            'HEAD_OF_RISK',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Risk Manager',
            'RISK_MANAGER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Risk Officer',
            'RISK_OFFICER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Head of Compliance',
            'HEAD_OF_COMPLIANCE',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Compliance Manager',
            'COMPLIANCE_MANAGER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Compliance Officer',
            'COMPLIANCE_OFFICER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'AML/CFT Compliance Officer',
            'AML_CFT_COMPLIANCE_OFFICER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Money Laundering Reporting Officer',
            'MLRO',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Fraud and Investigations Officer',
            'FRAUD_INVESTIGATIONS_OFFICER',
            'RISK_COMPLIANCE'
        );

        $addRole(
            'Data Protection Officer',
            'DATA_PROTECTION_OFFICER',
            'RISK_COMPLIANCE'
        );

        /*
         * ============================================================
         * 33. HUMAN RESOURCES / ADMINISTRATION
         * ============================================================
         */

        $addRole(
            'Head of Human Resources',
            'HEAD_OF_HUMAN_RESOURCES',
            'HR_ADMIN'
        );

        $addRole(
            'Human Resources Manager',
            'HR_MANAGER',
            'HR_ADMIN'
        );

        $addRole(
            'Human Resources Officer',
            'HR_OFFICER',
            'HR_ADMIN'
        );

        $addRole(
            'Administration Manager',
            'ADMINISTRATION_MANAGER',
            'HR_ADMIN'
        );

        $addRole(
            'Administration Officer',
            'ADMINISTRATION_OFFICER',
            'HR_ADMIN'
        );

        $addRole(
            'Training and Development Officer',
            'TRAINING_DEVELOPMENT_OFFICER',
            'HR_ADMIN'
        );

        /*
         * ============================================================
         * 34. MARKETING / COMMUNICATIONS / BUSINESS DEVELOPMENT
         * ============================================================
         */

        $addRole(
            'Head of Business Development',
            'HEAD_OF_BUSINESS_DEVELOPMENT',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Business Development Manager',
            'BUSINESS_DEVELOPMENT_MANAGER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Business Development Officer',
            'BUSINESS_DEVELOPMENT_OFFICER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Marketing Manager',
            'MARKETING_MANAGER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Marketing Officer',
            'MARKETING_OFFICER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Communications Manager',
            'COMMUNICATIONS_MANAGER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Communications Officer',
            'COMMUNICATIONS_OFFICER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Public Relations Officer',
            'PUBLIC_RELATIONS_OFFICER',
            'BUSINESS_DEVELOPMENT'
        );

        $addRole(
            'Digital Marketing Officer',
            'DIGITAL_MARKETING_OFFICER',
            'BUSINESS_DEVELOPMENT'
        );

        /*
         * ============================================================
         * 35. PROCUREMENT / STORES / ASSETS
         * ============================================================
         */

        $addRole(
            'Head of Procurement',
            'HEAD_OF_PROCUREMENT',
            'PROCUREMENT'
        );

        $addRole(
            'Procurement Manager',
            'PROCUREMENT_MANAGER',
            'PROCUREMENT'
        );

        $addRole(
            'Procurement Officer',
            'PROCUREMENT_OFFICER',
            'PROCUREMENT'
        );

        $addRole(
            'Procurement Assistant',
            'PROCUREMENT_ASSISTANT',
            'PROCUREMENT'
        );

        $addRole(
            'Stores Officer',
            'STORES_OFFICER',
            'PROCUREMENT'
        );

        $addRole(
            'Stores Assistant',
            'STORES_ASSISTANT',
            'PROCUREMENT'
        );

        $addRole(
            'Asset Management Officer',
            'ASSET_MANAGEMENT_OFFICER',
            'PROCUREMENT'
        );

        /*
         * ============================================================
         * 36. LEGAL / SECRETARIAL
         * ============================================================
         */

        $addRole(
            'Head of Legal',
            'HEAD_OF_LEGAL',
            'LEGAL'
        );

        $addRole(
            'Legal Manager',
            'LEGAL_MANAGER',
            'LEGAL'
        );

        $addRole(
            'Legal Officer',
            'LEGAL_OFFICER',
            'LEGAL'
        );

        $addRole(
            'Company Secretary',
            'COMPANY_SECRETARY',
            'LEGAL'
        );

        $addRole(
            'Co-operative Secretary',
            'COOPERATIVE_SECRETARY',
            'LEGAL'
        );

        /*
         * ============================================================
         * 37. INVESTMENTS
         * ============================================================
         */

        $addRole(
            'Head of Investments',
            'HEAD_OF_INVESTMENTS',
            'INVESTMENTS'
        );

        $addRole(
            'Investment Manager',
            'INVESTMENT_MANAGER',
            'INVESTMENTS'
        );

        $addRole(
            'Investment Officer',
            'INVESTMENT_OFFICER',
            'INVESTMENTS'
        );

        /*
         * ============================================================
         * 38. RECORDS / REGISTRY
         * ============================================================
         */

        $addRole(
            'Records Manager',
            'RECORDS_MANAGER',
            'RECORDS'
        );

        $addRole(
            'Records Officer',
            'RECORDS_OFFICER',
            'RECORDS'
        );

        $addRole(
            'Registry Officer',
            'REGISTRY_OFFICER',
            'RECORDS'
        );

        $addRole(
            'Registry Clerk',
            'REGISTRY_CLERK',
            'RECORDS'
        );

        /*
         * ============================================================
         * 39. SECURITY
         * ============================================================
         */

        $addRole(
            'Security Manager',
            'SECURITY_MANAGER',
            'SECURITY'
        );

        $addRole(
            'Security Officer',
            'SECURITY_OFFICER',
            'SECURITY'
        );

        /*
         * ============================================================
         * 40. GENERAL SUPPORT / ADMINISTRATIVE STAFF
         * ============================================================
         */

        $addRole(
            'Executive Assistant',
            'EXECUTIVE_ASSISTANT',
            'SUPPORT'
        );

        $addRole(
            'Personal Assistant',
            'PERSONAL_ASSISTANT',
            'SUPPORT'
        );

        $addRole(
            'Secretary',
            'SECRETARY',
            'SUPPORT'
        );

        $addRole(
            'Receptionist',
            'RECEPTIONIST',
            'SUPPORT'
        );

        $addRole(
            'Office Assistant',
            'OFFICE_ASSISTANT',
            'SUPPORT'
        );

        $addRole(
            'Clerk',
            'CLERK',
            'SUPPORT'
        );

        $addRole(
            'Driver',
            'DRIVER',
            'SUPPORT'
        );

        $addRole(
            'Messenger',
            'MESSENGER',
            'SUPPORT'
        );

        $addRole(
            'Cleaner',
            'CLEANER',
            'SUPPORT'
        );

        /*
         * ============================================================
         * 41. TEMPORARY / ACTING / DEVELOPMENT ROLES
         * ============================================================
         */

        $addRole(
            'Acting Chief Executive Officer',
            'ACTING_CEO',
            'TEMPORARY'
        );

        $addRole(
            'Acting General Manager',
            'ACTING_GENERAL_MANAGER',
            'TEMPORARY'
        );

        $addRole(
            'Acting Manager',
            'ACTING_MANAGER',
            'TEMPORARY'
        );

        $addRole(
            'Graduate Trainee',
            'GRADUATE_TRAINEE',
            'TEMPORARY'
        );

        $addRole(
            'Management Trainee',
            'MANAGEMENT_TRAINEE',
            'TEMPORARY'
        );

        $addRole(
            'Intern',
            'INTERN',
            'TEMPORARY'
        );

        $addRole(
            'Attaché',
            'ATTACHE',
            'TEMPORARY'
        );

        $addRole(
            'Consultant',
            'CONSULTANT',
            'TEMPORARY'
        );

        /*
         * ============================================================
         * INSERT
         * ============================================================
         *
         * Insert in chunks so that the migration remains safe even
         * as the default catalogue grows in future.
         */

        foreach (array_chunk($roles, 100) as $chunk) {
            DB::table('sacco_member_classifications')->insert($chunk);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacco_member_classifications');
    }
};