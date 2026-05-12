
// Modular validator for maintenance form
export function validateMaintenanceForm(data) {
    const errors = [];
    if (!data.ticket_number || !data.ticket_number.trim()) errors.push('Ticket Number is required.');
    if (!data.room_id || !String(data.room_id).trim()) errors.push('Room ID is required.');
    if (data.assigned_to === undefined || data.assigned_to === null || String(data.assigned_to).trim() === '') errors.push('Assigned To is required.');
    if (!data.date_reported) errors.push('Date Reported is required.');
    if (data.reported_by === undefined || data.reported_by === null || String(data.reported_by).trim() === '') errors.push('Reported By is required.');
    if (!data.status) errors.push('Status is required.');

    if (data.ticket_number && data.ticket_number.trim().length > 20) {
        errors.push('Ticket Number must be 20 characters or less.');
    }

    // room id should be numeric id
    if (data.room_id && !/^\d+$/.test(String(data.room_id))) {
        errors.push('Room ID must be a room ID.');
    }

    // date format check YYYY-MM-DD
    if (data.date_reported && !/^\d{4}-\d{2}-\d{2}$/.test(data.date_reported)) {
        errors.push('Date Reported must be in YYYY-MM-DD format.');
    }
    if (data.date_reported && /^\d{4}-\d{2}-\d{2}$/.test(data.date_reported)) {
        const [y, m, d] = data.date_reported.split('-').map(Number);
        const parsed = new Date(data.date_reported + 'T00:00:00');
        const validDate = parsed.getFullYear() === y && (parsed.getMonth() + 1) === m && parsed.getDate() === d;
        if (!validDate) errors.push('Date Reported must be a valid calendar date.');
    }

    // assigned_to and reported_by should be numeric IDs
    if (data.assigned_to && !/^\d+$/.test(String(data.assigned_to))) {
        errors.push('Assigned To must be a staff ID.');
    }
    if (data.reported_by && !/^\d+$/.test(String(data.reported_by))) {
        errors.push('Reported By must be a student ID.');
    }
    if (data.status && !['Resolved', 'Not Resolved'].includes(data.status)) {
        errors.push('Status must be either Resolved or Not Resolved.');
    }

    return errors;
}

export async function submitMaintenance(url, data) {
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        });
        const text = await resp.text();
        try { return JSON.parse(text); } catch (err) { return { success: false, errors: ['Invalid JSON response', text] }; }
    } catch (err) {
        return { success: false, errors: [err.message] };
    }
}

export async function fetchStaffs(url) {
    try {
        const r = await fetch(url);
        const text = await r.text();


        try { return JSON.parse(text); } catch (err) { return { success: false, errors: ['Invalid JSON response from staffs', text] }; }
    } catch (err) {
        return { success: false, errors: [err.message] };
    }
}

export async function fetchStudents(url) {
    try {
        const r = await fetch(url);
        const text = await r.text();
        try { return JSON.parse(text); } catch (err) { return { success: false, errors: ['Invalid JSON response from students', text] }; }
    } catch (err) {
        return { success: false, errors: [err.message] };
    }
}

export async function fetchRooms(url) {
    try {
        const r = await fetch(url);
        const text = await r.text();
        try { return JSON.parse(text); } catch (err) { return { success: false, errors: ['Invalid JSON response from rooms', text] }; }
    } catch (err) {
        return { success: false, errors: [err.message] };
    }
}
