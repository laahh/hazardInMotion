@extends('layouts.masterMotionHazardAdmin')

@section('title', 'Notification Settings - Beraucoal')

@section('css')
<style>
    .settings-header {
        margin-bottom: 24px;
    }

    .settings-title {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .settings-subtitle {
        font-size: 14px;
        color: #6b7280;
    }

    .settings-section {
        margin-bottom: 32px;
    }

    .settings-section-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
    }
</style>
@endsection

@section('content')
<div class="settings-header">
    <h1 class="settings-title">Notification Settings</h1>
    <p class="settings-subtitle">Configure how you receive and manage alerts</p>
</div>

<form id="settingsForm">
    @csrf
    
    <!-- Notification Channels -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Notification Channels</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="emailNotifications" 
                           name="email_notifications" {{ $settings['email_notifications'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="emailNotifications">
                        <strong>Email Notifications</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Receive alerts via email</p>
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="smsNotifications" 
                           name="sms_notifications" {{ $settings['sms_notifications'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="smsNotifications">
                        <strong>SMS Notifications</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Receive alerts via SMS</p>
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="pushNotifications" 
                           name="push_notifications" {{ $settings['push_notifications'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="pushNotifications">
                        <strong>Push Notifications</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Receive browser push notifications</p>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Preferences -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Alert Preferences</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="criticalAlertsOnly" 
                           name="critical_alerts_only" {{ $settings['critical_alerts_only'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="criticalAlertsOnly">
                        <strong>Critical Alerts Only</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Only receive notifications for critical severity alerts</p>
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notificationSound" 
                           name="notification_sound" {{ $settings['notification_sound'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="notificationSound">
                        <strong>Notification Sound</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Play sound when new alerts arrive</p>
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoAcknowledge" 
                           name="auto_acknowledge" {{ $settings['auto_acknowledge'] ? 'checked' : '' }}>
                    <label class="form-check-label" for="autoAcknowledge">
                        <strong>Auto Acknowledge</strong>
                        <p class="text-muted mb-0" style="font-size: 13px;">Automatically acknowledge alerts after viewing</p>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Retention -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Data Retention</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="alertRetentionDays" class="form-label">
                    <strong>Alert Retention Period (Days)</strong>
                </label>
                <input type="number" class="form-control" id="alertRetentionDays" 
                       name="alert_retention_days" value="{{ $settings['alert_retention_days'] }}" 
                       min="1" max="365">
                <p class="text-muted mb-0" style="font-size: 13px;">How long to keep alert history (1-365 days)</p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('realtime-alerts.index') }}" class="btn btn-secondary">
            Cancel
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="material-icons-outlined">save</i> Save Settings
        </button>
    </div>
</form>
@endsection

@section('scripts')
<script>
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Here you would make an API call to save settings
        fetch('{{ route("realtime-alerts.settings.save") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Settings saved successfully!');
            } else {
                alert('Failed to save settings');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving settings');
        });
    });
</script>
@endsection

