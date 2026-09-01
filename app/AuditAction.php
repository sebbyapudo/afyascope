<?php

namespace App;

enum AuditAction: string
{
    case AdministratorBootstrapped = 'administrator.bootstrapped';
    case StaffCreated = 'staff.created';
    case StaffUpdated = 'staff.updated';
    case PatientRegistered = 'patient.registered';
    case PatientUpdated = 'patient.updated';
    case VisitCreated = 'visit.created';
    case AppointmentCreated = 'appointment.created';
    case AppointmentRescheduled = 'appointment.rescheduled';
    case AppointmentCancelled = 'appointment.cancelled';
    case AppointmentNoShow = 'appointment.no_show';
    case AppointmentVisitLinked = 'appointment.visit_linked';
    case BillCreated = 'bill.created';
    case PaymentRecorded = 'payment.recorded';
    case ReceiptIssued = 'receipt.issued';
    case ConsultationFinancialCleared = 'consultation.financial_cleared';
    case VisitCheckedIn = 'visit.checked_in';

    public function displayName(): string
    {
        return match ($this) {
            self::AdministratorBootstrapped => 'Initial Administrator created',
            self::StaffCreated => 'Staff account created',
            self::StaffUpdated => 'Staff account updated',
            self::PatientRegistered => 'Patient registered',
            self::PatientUpdated => 'Patient demographics updated',
            self::VisitCreated => 'Visit created',
            self::AppointmentCreated => 'Appointment created',
            self::AppointmentRescheduled => 'Appointment rescheduled',
            self::AppointmentCancelled => 'Appointment cancelled',
            self::AppointmentNoShow => 'Appointment marked no-show',
            self::AppointmentVisitLinked => 'Appointment linked to Visit',
            self::BillCreated => 'Bill created',
            self::PaymentRecorded => 'Payment recorded',
            self::ReceiptIssued => 'Receipt issued',
            self::ConsultationFinancialCleared => 'Consultation financially cleared',
            self::VisitCheckedIn => 'Visit checked in',
        };
    }
}
