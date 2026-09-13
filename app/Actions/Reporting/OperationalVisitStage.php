<?php

namespace App\Actions\Reporting;

enum OperationalVisitStage: string
{
    case AwaitingConsultationBilling = 'awaiting_consultation_billing';
    case AwaitingConsultationPayment = 'awaiting_consultation_payment';
    case AwaitingConsultationFinancialClearance = 'awaiting_consultation_financial_clearance';
    case ReadyForReceptionCheckIn = 'ready_for_reception_check_in';
    case ReadyForDoctorConsultation = 'ready_for_doctor_consultation';
    case ConsultationInProgress = 'consultation_in_progress';
    case AwaitingProcedureBilling = 'awaiting_procedure_billing';
    case AwaitingProcedurePayment = 'awaiting_procedure_payment';
    case AwaitingProcedureFinancialClearance = 'awaiting_procedure_financial_clearance';
    case ReadyForNursingPreparation = 'ready_for_nursing_preparation';
    case NursingPreparationInProgress = 'nursing_preparation_in_progress';
    case ReadyForDoctorProcedure = 'ready_for_doctor_procedure';
    case ProcedureInProgress = 'procedure_in_progress';
    case ReadyForNursingRecovery = 'ready_for_nursing_recovery';
    case RecoveryInProgress = 'recovery_in_progress';
    case DoctorReviewRequired = 'doctor_review_required';
    case ReadyForDischarge = 'ready_for_discharge';
    case Completed = 'completed';

    public function displayName(): string
    {
        return match ($this) {
            self::AwaitingConsultationBilling => 'Awaiting consultation billing',
            self::AwaitingConsultationPayment => 'Awaiting consultation payment',
            self::AwaitingConsultationFinancialClearance => 'Awaiting consultation financial clearance',
            self::ReadyForReceptionCheckIn => 'Ready for Reception check-in',
            self::ReadyForDoctorConsultation => 'Ready for Doctor consultation',
            self::ConsultationInProgress => 'Consultation in progress',
            self::AwaitingProcedureBilling => 'Awaiting procedure billing',
            self::AwaitingProcedurePayment => 'Awaiting procedure payment',
            self::AwaitingProcedureFinancialClearance => 'Awaiting procedure financial clearance',
            self::ReadyForNursingPreparation => 'Ready for Nursing preparation',
            self::NursingPreparationInProgress => 'Nursing preparation in progress',
            self::ReadyForDoctorProcedure => 'Ready for Doctor procedure',
            self::ProcedureInProgress => 'Procedure in progress',
            self::ReadyForNursingRecovery => 'Ready for Nursing recovery',
            self::RecoveryInProgress => 'Recovery in progress',
            self::DoctorReviewRequired => 'Doctor review required',
            self::ReadyForDischarge => 'Ready for discharge',
            self::Completed => 'Completed',
        };
    }
}
