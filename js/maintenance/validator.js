
// Modular validator for maintenance form
export function validateMaintenanceForm(data) {
    const errors = [];
    if (!data.ticket_number || !data.ticket_number.trim()) errors.push('Ticket Number is required.');
    if (!data.room_id || !data.room_id.trim()) errors.push('Room ID is required.');
    if (!data.assigned_to || !data.assigned_to.trim()) errors.push('Assigned To is required.');
    if (!data.date_reported) errors.push('Date Reported is required.');
    if (!data.reported_by || !data.reported_by.trim()) errors.push('Reported By is required.');
    if (!data.status) errors.push('Status is required.');

    // room id simple pattern
    if (data.room_id && !/^[A-Za-z0-9\-]+$/.test(data.room_id)) {
        errors.push('Room ID contains invalid characters.');
    }

    // date format check YYYY-MM-DD
    if (data.date_reported && !/^\d{4}-\d{2}-\d{2}$/.test(data.date_reported)) {
        errors.push('Date Reported must be in YYYY-MM-DD format.');
    }

    return errors;
}

export async function submitMaintenance(url, data) {
    const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    });
    return resp.json();
}
