<?php
    namespace App\Helpers;

    /**
     * Single source of truth for form-type → human label mapping.
     *
     * Previously this array was copy-pasted in:
     *   - FormController::show()
     *   - FormController::mySubmissions()
     *   - FormController::allRequests()
     *   - ApprovalController::inbox()
     *   - views/forms/show.php
     *   - views/approvals/inbox.php
     *
     * Use: \App\Helpers\FormLabels::get($formType)
     *      \App\Helpers\FormLabels::all()
     *      \App\Helpers\FormLabels::statusLabel($status)   ← NEW
     */
    class FormLabels
    {
        /**
         * Status → human-readable display label.
         *
         * FIX: Previously every view called ucfirst(str_replace('_', ' ', $status))
         * which produced technical strings like "Checker approved" or "Finance reviewed".
         * This map returns concise, user-facing labels instead.
         * Use FormLabels::statusLabel($status) everywhere a status is displayed.
         */
        private const STATUS_LABELS = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'immediatehead_approved' => 'With Immediate Head',
            'process_approved' => 'Processing',
            'department_reviewed' => 'Dept. Review',
            'finance_reviewed' => 'Finance Review',
            'supervisor_reviewed' => 'With Supervisor',
            'department_checked' => 'Dept. Check',
            'final_approved' => 'Final Approved',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        /** Human-readable label for a status string. Falls back to ucwords transform. */
        public static function statusLabel(string $status): string {
            return self::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', $status));
        }

        /** Full status → label map (for views that pass it as a template variable). */
        public static function allStatusLabels(): array {
            return self::STATUS_LABELS;
        }

        /**
         * Status → Bootstrap/app badge colour class.
         * Single source of truth — replaces the local $badgeMap in every view.
         */
        private const BADGE_MAP = [
            'draft' => 'secondary',
            'submitted' => 'primary',
            'immediatehead_approved' => 'info',
            'process_approved' => 'info',
            'department_reviewed' => 'info',
            'finance_reviewed' => 'warning',
            'supervisor_reviewed' => 'info',
            'department_checked' => 'info',
            'final_approved' => 'success',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'dark',
        ];

        /** Badge class for a single status string. Defaults to 'secondary'. */
        public static function badgeClass(string $status): string {
            return self::BADGE_MAP[$status] ?? 'secondary';
        }

        /** Full badge map (for views that pass it to a template variable). */
        public static function allBadges(): array {
            return self::BADGE_MAP;
        }

        private const STEP_LABELS_ADMIN = [
            1 => 'Request Submitted',
            2 => 'Supervisor Verification',
            3 => 'Review Approval',
            4 => 'Final Authorization',
        ];

        private const STEP_LABELS_FINANCE = [
            1 => 'Request Submitted',
            2 => 'Supervisor Verification',
            3 => 'Accounting Validation',
            4 => 'Financial Assessment',
            5 => 'Final Authorization',
        ];

        private const STEP_LABELS_REIMBURSEMENT = [
            1 => 'Request Submitted',
            2 => 'Supervisor Verification',
            3 => 'HR Verification',
            4 => 'Accounting Validation',
            5 => 'Financial Assessment',
            6 => 'Final Authorization',
        ];

        private const ADMIN_FORMS = ['overtime_authorization', 'leave_application', 'vehicle_request'];

        private const LABELS = [
            'advance_payment' => 'Advance Payment',
            'overtime_authorization' => 'Overtime Authorization',
            'request_for_payment' => 'Request for Payment',
            'leave_application' => 'Leave Application',
            'reimbursement' => 'Reimbursement',
            'liquidation' => 'Liquidation',
            'vehicle_request' => 'Vehicle Request',
        ];

        public static function stepLabel(int $sequence, string $formType = ''): string {
            if ($formType === 'vehicle_request') {
                return [
                    1 => 'Request Submitted',
                    2 => 'Supervisor Verification',
                    3 => 'Admin Evaluation',
                    4 => 'Final Admin Approval',
                ][$sequence] ?? 'Step ' . $sequence;
            }
            $map = $formType === 'reimbursement'
                ? self::STEP_LABELS_REIMBURSEMENT
                : (in_array($formType, self::ADMIN_FORMS, true)
                    ? self::STEP_LABELS_ADMIN
                    : self::STEP_LABELS_FINANCE);
            return $map[$sequence] ?? 'Step ' . $sequence;
        }

        /**
         * Return the label for a single form type, falling back to a
         * humanised version of the raw type string if not found.
         */
        public static function get(string $type): string {
            return self::LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
        }

        /**
         * Return the full label map (for views that need to pass it to templates).
         */
        public static function all(): array {
            return self::LABELS;
        }

        /**
         * Check if a form type belongs to the Administrative category.
         */
        public static function isAdminForm(string $type): bool {
            return in_array($type, self::ADMIN_FORMS, true);
        }

        private const VERBS_ADMIN = [
            1 => 'Submit',
            2 => 'Check',
            3 => 'Review',
            4 => 'Approve',   // Grant Approval — Final Approver, completes the form
        ]; 

        private const VERBS_FINANCE = [
            1 => 'Submit',
            2 => 'Process',
            3 => 'Process',
            4 => 'Process',
            5 => 'Process',   // Grant Approval — Final Approver, completes the form
        ];

        /**
         * Returns a context-aware verb for approval action buttons based on the
         * sequence number and form category. Falls back to the Finance map when
         * no form type is given, to preserve prior behaviour for existing callers.
         */
        public static function verb(int $sequence, string $formType = ''): string {
            $map = self::isAdminForm($formType) ? self::VERBS_ADMIN : self::VERBS_FINANCE;
            return $map[$sequence] ?? 'Review';
        }

        /**
         * Resolve the human-readable pipeline stage.
         * 
         * @param string $status The raw form status
         * @param int|null $currentStep The earliest pending sequence number
         * @param string $formType The type of form to resolve correct labels
         */
        public static function currentStage(string $status, ?int $currentStep, string $formType = ''): string {
            if ($status === 'draft') return 'Draft';
            if ($status === 'rejected') return 'Rejected';
            if ($status === 'completed') return 'Completed';
            if ($status === 'cancelled') return 'Cancelled'; 

            // Use the pending sequence label if available
            if ($currentStep) { 
                return self::stepLabel((int)$currentStep, $formType);
            }

            return self::statusLabel($status);
        } 
    }