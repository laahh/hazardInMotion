<?php

declare(strict_types=1);

use App\Http\Controllers\EmergencyResponse\Dashboard\DashboardController;
use App\Http\Controllers\EmergencyResponse\Equipment\EquipmentController;
use App\Http\Controllers\EmergencyResponse\Incident\IncidentController;
use App\Http\Controllers\EmergencyResponse\Inspection\InspectionController;
use App\Http\Controllers\EmergencyResponse\Inspection\InspectionFindingController;
use App\Http\Controllers\EmergencyResponse\Maintenance\MaintenanceController;
use App\Http\Controllers\EmergencyResponse\Maintenance\MaintenanceScheduleController;
use App\Http\Controllers\EmergencyResponse\Maintenance\SparePartController;
use App\Http\Controllers\EmergencyResponse\Maintenance\WorkOrderController;
use App\Http\Controllers\EmergencyResponse\Manpower\AttendanceController;
use App\Http\Controllers\EmergencyResponse\Manpower\CertificationController;
use App\Http\Controllers\EmergencyResponse\Manpower\EmployeeCertificationController;
use App\Http\Controllers\EmergencyResponse\Manpower\EmployeeController;
use App\Http\Controllers\EmergencyResponse\Manpower\EmployeeTrainingController;
use App\Http\Controllers\EmergencyResponse\Manpower\ManpowerController;
use App\Http\Controllers\EmergencyResponse\Manpower\TrainingController;
use App\Http\Controllers\EmergencyResponse\Notification\AlertController;
use App\Http\Controllers\EmergencyResponse\Notification\NotificationController;
use App\Http\Controllers\EmergencyResponse\Report\ReportController;
use App\Http\Controllers\EmergencyResponse\Response\ResponseController;
use App\Http\Controllers\EmergencyResponse\SafetyDevice\SafetyDeviceController;
use App\Http\Controllers\EmergencyResponse\Shared\ScanController;
use App\Http\Controllers\EmergencyResponse\MasterData\AreaController;
use App\Http\Controllers\EmergencyResponse\MasterData\CertificationTypeController;
use App\Http\Controllers\EmergencyResponse\MasterData\ChecklistTemplateController;
use App\Http\Controllers\EmergencyResponse\MasterData\DepartmentController;
use App\Http\Controllers\EmergencyResponse\MasterData\EmailTemplateController;
use App\Http\Controllers\EmergencyResponse\MasterData\EmergencyUnitController;
use App\Http\Controllers\EmergencyResponse\MasterData\EquipmentCategoryController;
use App\Http\Controllers\EmergencyResponse\MasterData\EscalationMatrixController;
use App\Http\Controllers\EmergencyResponse\MasterData\IncidentTypeController;
use App\Http\Controllers\EmergencyResponse\MasterData\LocationController;
use App\Http\Controllers\EmergencyResponse\MasterData\MaintenanceTypeController;
use App\Http\Controllers\EmergencyResponse\MasterData\MasterDataController;
use App\Http\Controllers\EmergencyResponse\MasterData\NotificationTemplateController;
use App\Http\Controllers\EmergencyResponse\MasterData\PriorityLevelController;
use App\Http\Controllers\EmergencyResponse\MasterData\SafetyDeviceTypeController;
use App\Http\Controllers\EmergencyResponse\MasterData\SeverityLevelController;
use App\Http\Controllers\EmergencyResponse\MasterData\ShiftController;
use App\Http\Controllers\EmergencyResponse\MasterData\SiteController;
use App\Http\Controllers\EmergencyResponse\MasterData\SlaController;
use App\Http\Controllers\EmergencyResponse\MasterData\TrainingTypeController;
use App\Http\Controllers\EmergencyResponse\MasterData\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('master-data')->name('master-data.')->group(function (): void {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');

        // Resource berbasis modal (index + create/edit sekaligus dalam modal) — tidak ada halaman create/edit terpisah.
        $modalOnly = ['show', 'create', 'edit'];

        Route::resource('sites', SiteController::class)->except($modalOnly);
        Route::resource('locations', LocationController::class)->except($modalOnly);
        Route::resource('areas', AreaController::class)->except($modalOnly);
        Route::resource('departments', DepartmentController::class)->except($modalOnly);
        Route::resource('emergency-units', EmergencyUnitController::class)->except($modalOnly)->parameters(['emergency-units' => 'id']);
        Route::resource('equipment-categories', EquipmentCategoryController::class)->except($modalOnly)->parameters(['equipment-categories' => 'id']);
        Route::resource('safety-device-types', SafetyDeviceTypeController::class)->except($modalOnly)->parameters(['safety-device-types' => 'id']);
        Route::resource('incident-types', IncidentTypeController::class)->except($modalOnly)->parameters(['incident-types' => 'id']);
        Route::resource('maintenance-types', MaintenanceTypeController::class)->except($modalOnly)->parameters(['maintenance-types' => 'id']);
        Route::resource('training-types', TrainingTypeController::class)->except($modalOnly)->parameters(['training-types' => 'id']);
        Route::resource('certification-types', CertificationTypeController::class)->except($modalOnly)->parameters(['certification-types' => 'id']);
        Route::resource('severity-levels', SeverityLevelController::class)->except($modalOnly)->parameters(['severity-levels' => 'id']);
        Route::resource('priority-levels', PriorityLevelController::class)->except($modalOnly)->parameters(['priority-levels' => 'id']);
        Route::resource('vendors', VendorController::class)->except($modalOnly);
        Route::resource('shifts', ShiftController::class)->except($modalOnly);
        Route::resource('slas', SlaController::class)->except($modalOnly);
        Route::resource('escalation-matrices', EscalationMatrixController::class)->except($modalOnly)->parameters(['escalation-matrices' => 'escalation_matrix']);

        // Resource dengan halaman create/edit penuh (form panjang, tidak cocok di modal).
        Route::resource('email-templates', EmailTemplateController::class)->except(['show'])->parameters(['email-templates' => 'email_template']);
        Route::resource('notification-templates', NotificationTemplateController::class)->except(['show'])->parameters(['notification-templates' => 'notification_template']);
        Route::resource('checklist-templates', ChecklistTemplateController::class)->except(['show'])->parameters(['checklist-templates' => 'checklist_template']);
    });

    Route::prefix('scan')->name('scan.')->group(function (): void {
        Route::get('/', [ScanController::class, 'index'])->name('index');
        Route::post('/', [ScanController::class, 'lookup'])->name('lookup');
        Route::get('/{code}', [ScanController::class, 'show'])->name('show');
    });

    Route::prefix('equipment')->name('equipment.')->group(function (): void {
        Route::get('/export', [EquipmentController::class, 'export'])->name('export');
        Route::get('/import-template', [EquipmentController::class, 'importTemplate'])->name('import-template');
        Route::post('/import', [EquipmentController::class, 'import'])->name('import');
        Route::get('/{equipment}/qr', [EquipmentController::class, 'qrSvg'])->name('qr');
        Route::get('/{equipment}/print', [EquipmentController::class, 'print'])->name('print');
        Route::post('/{equipment}/documents', [EquipmentController::class, 'storeDocument'])->name('documents.store');
        Route::delete('/{equipment}/documents/{document}', [EquipmentController::class, 'destroyDocument'])->name('documents.destroy');
    });
    Route::resource('equipment', EquipmentController::class)->except(['show'])->parameters(['equipment' => 'equipment']);
    Route::get('equipment/{equipment}', [EquipmentController::class, 'show'])->name('equipment.show');

    Route::prefix('safety-device')->name('safety-device.')->group(function (): void {
        Route::get('/export', [SafetyDeviceController::class, 'export'])->name('export');
        Route::get('/{safety_device}/qr', [SafetyDeviceController::class, 'qrSvg'])->name('qr');
        Route::get('/{safety_device}/print', [SafetyDeviceController::class, 'print'])->name('print');
        Route::post('/{safety_device}/documents', [SafetyDeviceController::class, 'storeDocument'])->name('documents.store');
        Route::delete('/{safety_device}/documents/{document}', [SafetyDeviceController::class, 'destroyDocument'])->name('documents.destroy');
    });
    Route::resource('safety-device', SafetyDeviceController::class)->except(['show'])->parameters(['safety-device' => 'safety_device']);
    Route::get('safety-device/{safety_device}', [SafetyDeviceController::class, 'show'])->name('safety-device.show');

    Route::prefix('inspection')->name('inspection.')->group(function (): void {
        Route::get('/pick-target', [InspectionController::class, 'pickTarget'])->name('pick-target');
        Route::get('/create', [InspectionController::class, 'create'])->name('create');
        Route::get('/findings', [InspectionFindingController::class, 'index'])->name('findings.index');
        Route::post('/findings/{finding}/assign', [InspectionFindingController::class, 'assign'])->name('findings.assign');
        Route::post('/findings/{finding}/resolve', [InspectionFindingController::class, 'resolve'])->name('findings.resolve');
        Route::get('/{inspection}/pdf', [InspectionController::class, 'pdf'])->name('pdf');
        Route::post('/{inspection}/approve', [InspectionController::class, 'approve'])->name('approve');
        Route::post('/{inspection}/reject', [InspectionController::class, 'reject'])->name('reject');
    });
    Route::get('inspection', [InspectionController::class, 'index'])->name('inspection.index');
    Route::post('inspection', [InspectionController::class, 'store'])->name('inspection.store');
    Route::get('inspection/{inspection}', [InspectionController::class, 'show'])->name('inspection.show');

    Route::prefix('incident')->name('incident.')->group(function (): void {
        Route::get('/export', [IncidentController::class, 'export'])->name('export');
        Route::get('/create', [IncidentController::class, 'create'])->name('create');
        Route::get('/{incident}/edit', [IncidentController::class, 'edit'])->name('edit');
        Route::put('/{incident}', [IncidentController::class, 'update'])->name('update');
        Route::get('/{incident}/pdf', [IncidentController::class, 'pdf'])->name('pdf');
        Route::post('/{incident}/confirm', [IncidentController::class, 'confirm'])->name('confirm');
        Route::post('/{incident}/timestamp', [IncidentController::class, 'updateTimestamp'])->name('timestamp');
        Route::post('/{incident}/resolve', [IncidentController::class, 'resolve'])->name('resolve');
        Route::post('/{incident}/close', [IncidentController::class, 'close'])->name('close');
        Route::post('/{incident}/dismiss-duplicate', [IncidentController::class, 'dismissDuplicate'])->name('dismiss-duplicate');
        Route::post('/{incident}/assign-pic', [IncidentController::class, 'assignPic'])->name('assign-pic');
        Route::post('/{incident}/comments', [IncidentController::class, 'addComment'])->name('comments.store');
        Route::post('/{incident}/victims', [IncidentController::class, 'storeVictim'])->name('victims.store');
        Route::delete('/{incident}/victims/{victim}', [IncidentController::class, 'destroyVictim'])->name('victims.destroy');
        Route::post('/{incident}/attachments', [IncidentController::class, 'storeAttachment'])->name('attachments.store');
        Route::delete('/{incident}/attachments/{attachment}', [IncidentController::class, 'destroyAttachment'])->name('attachments.destroy');

        Route::post('/{incident}/units', [ResponseController::class, 'dispatchUnit'])->name('units.store');
        Route::put('/{incident}/units/{unit}/status', [ResponseController::class, 'updateUnitStatus'])->name('units.status');
        Route::post('/{incident}/personnel', [ResponseController::class, 'storePersonnel'])->name('personnel.store');
        Route::delete('/{incident}/personnel/{personnel}', [ResponseController::class, 'destroyPersonnel'])->name('personnel.destroy');
        Route::post('/{incident}/equipment-usage', [ResponseController::class, 'storeEquipmentUsage'])->name('equipment-usage.store');
        Route::delete('/{incident}/equipment-usage/{usage}', [ResponseController::class, 'destroyEquipmentUsage'])->name('equipment-usage.destroy');
    });
    Route::get('incident', [IncidentController::class, 'index'])->name('incident.index');
    Route::post('incident', [IncidentController::class, 'store'])->name('incident.store');
    Route::get('incident/{incident}', [IncidentController::class, 'show'])->name('incident.show');

    Route::get('response', [ResponseController::class, 'index'])->name('response.index');

    Route::prefix('maintenance')->name('maintenance.')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');

        $modalOnly = ['show', 'create', 'edit'];
        Route::resource('spare-parts', SparePartController::class)->except($modalOnly)->parameters(['spare-parts' => 'spare_part']);
        Route::resource('schedules', MaintenanceScheduleController::class)->except($modalOnly)->parameters(['schedules' => 'schedule']);
    });

    Route::prefix('work-order')->name('work-order.')->group(function (): void {
        Route::get('/kanban', [WorkOrderController::class, 'kanban'])->name('kanban');
        Route::get('/calendar', [WorkOrderController::class, 'calendar'])->name('calendar');
        Route::get('/export', [WorkOrderController::class, 'export'])->name('export');
        Route::get('/create', [WorkOrderController::class, 'create'])->name('create');
        Route::get('/{work_order}/pdf', [WorkOrderController::class, 'pdf'])->name('pdf');
        Route::post('/{work_order}/approve', [WorkOrderController::class, 'approve'])->name('approve');
        Route::post('/{work_order}/assign', [WorkOrderController::class, 'assign'])->name('assign');
        Route::post('/{work_order}/start', [WorkOrderController::class, 'start'])->name('start');
        Route::post('/{work_order}/hold', [WorkOrderController::class, 'hold'])->name('hold');
        Route::post('/{work_order}/resume', [WorkOrderController::class, 'resume'])->name('resume');
        Route::post('/{work_order}/complete', [WorkOrderController::class, 'complete'])->name('complete');
        Route::post('/{work_order}/verify', [WorkOrderController::class, 'verify'])->name('verify');
        Route::post('/{work_order}/close', [WorkOrderController::class, 'close'])->name('close');
        Route::post('/{work_order}/spare-parts', [WorkOrderController::class, 'storeSparePart'])->name('spare-parts.store');
        Route::delete('/{work_order}/spare-parts/{spare_part}', [WorkOrderController::class, 'destroySparePart'])->name('spare-parts.destroy');
        Route::post('/{work_order}/documents', [WorkOrderController::class, 'storeDocument'])->name('documents.store');
        Route::delete('/{work_order}/documents/{document}', [WorkOrderController::class, 'destroyDocument'])->name('documents.destroy');
    });
    Route::get('work-order', [WorkOrderController::class, 'index'])->name('work-order.index');
    Route::post('work-order', [WorkOrderController::class, 'store'])->name('work-order.store');
    Route::get('work-order/{work_order}', [WorkOrderController::class, 'show'])->name('work-order.show');

    Route::prefix('notification')->name('notification.')->group(function (): void {
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
        Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
        Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markRead'])->name('mark-read');
    });
    Route::get('notification', [NotificationController::class, 'index'])->name('notification.index');

    Route::prefix('manpower')->name('manpower.')->group(function (): void {
        Route::get('/', [ManpowerController::class, 'index'])->name('index');

        Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::post('/employees/{employee}/trainings', [EmployeeTrainingController::class, 'store'])->name('employees.trainings.store');
        Route::delete('/employees/{employee}/trainings/{training}', [EmployeeTrainingController::class, 'destroy'])->name('employees.trainings.destroy');
        Route::post('/employees/{employee}/certifications', [EmployeeCertificationController::class, 'store'])->name('employees.certifications.store');
        Route::delete('/employees/{employee}/certifications/{certification}', [EmployeeCertificationController::class, 'destroy'])->name('employees.certifications.destroy');
        Route::resource('employees', EmployeeController::class)->except(['show'])->parameters(['employees' => 'employee']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/{employee}', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/{employee}/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('/attendance/{employee}/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

        $modalOnly = ['show', 'create', 'edit'];
        Route::resource('trainings', TrainingController::class)->except($modalOnly)->parameters(['trainings' => 'training']);
        Route::resource('certifications', CertificationController::class)->except($modalOnly)->parameters(['certifications' => 'certification']);
    });

    Route::prefix('report')->name('report.')->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/equipment', [ReportController::class, 'equipment'])->name('equipment');
        Route::get('/equipment/export', [ReportController::class, 'equipmentExport'])->name('equipment.export');
        Route::get('/inspections', [ReportController::class, 'inspections'])->name('inspections');
        Route::get('/incidents', [ReportController::class, 'incidents'])->name('incidents');
        Route::get('/incidents/export', [ReportController::class, 'incidentsExport'])->name('incidents.export');
        Route::get('/equipment-usage', [ReportController::class, 'equipmentUsage'])->name('equipment-usage');
        Route::get('/work-orders', [ReportController::class, 'workOrders'])->name('work-orders');
        Route::get('/attendance', [ReportController::class, 'attendance'])->name('attendance');
        Route::get('/training-certification', [ReportController::class, 'trainingCertification'])->name('training-certification');
    });

    // Fase 9 (hardening) akan mendaftarkan route berikut, mis.:
    // Route::resource('audit-log', AuditLog\AuditLogController::class)->only(['index', 'show']);
    // Route::resource('settings', Settings\SettingsController::class)->only(['index', 'update']);
});
