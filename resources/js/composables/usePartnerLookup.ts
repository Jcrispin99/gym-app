interface PartnerData {
    name?: string;
    business_name?: string;
    first_name?: string;
    last_name?: string;
    address?: string;
    source?: string;
    partner?: any;
    // Add other potential fields from API response
    [key: string]: any;
}

interface PartnerForm {
    document_type: string;
    document_number: string;
    business_name?: string;
    first_name?: string;
    last_name?: string;
    name?: string; // Sometimes used as fallback
    address?: string;
    email?: string;
    phone?: string;
    mobile?: string;
    district?: string;
    province?: string;
    department?: string;
    // Member specific
    birth_date?: string;
    gender?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    blood_type?: string;
    allergies?: string;
    medical_notes?: string;
    [key: string]: any;
}

export function usePartnerLookup() {
    const handlePartnerFound = (data: PartnerData, form: PartnerForm) => {
        console.log('Partner found:', data);

        // 1. Manejo de Razón Social (Prioridad para RUC)
        if (data.business_name) {
            form.business_name = data.business_name;
        }

        // 2. Manejo de Nombres Personales (Prioridad para DNI)
        if (data.first_name) form.first_name = data.first_name;
        if (data.last_name) form.last_name = data.last_name;

        // 3. Fallback inteligente usando campo 'name' genérico
        if (data.name) {
            // Caso RUC: Si no hay razón social explícita, usar name
            if (form.document_type === 'RUC' && !form.business_name) {
                form.business_name = data.name;
            } 
            // Caso Persona: Si faltan nombre o apellido, intentar parsear 'name'
            else if (!form.first_name || !form.last_name) {
                const parts = data.name.trim().split(/\s+/);
                
                // Heurística simple: 
                // Si son 3 partes: Nombre ApellidoP ApellidoM
                // Si son 4 partes: Nombre1 Nombre2 ApellidoP ApellidoM
                // Esto es imperfecto pero mejor que nada.
                if (parts.length >= 3) {
                    // Asumimos los dos últimos son apellidos
                    form.last_name = parts.slice(-2).join(' ');
                    form.first_name = parts.slice(0, -2).join(' ');
                } else if (parts.length === 2) {
                    form.first_name = parts[0];
                    form.last_name = parts[1];
                } else {
                    form.first_name = data.name; // Fallback total
                }
            }
        }

        // 4. Dirección
        if (data.address) {
            form.address = data.address;
        }

        // 5. Datos existentes en BD (Sobrescriben o complementan)
        if (data.source === 'db' && data.partner) {
            const p = data.partner;
            
            // Campos comunes
            if (p.email) form.email = p.email;
            if (p.phone) form.phone = p.phone;
            if (p.mobile) form.mobile = p.mobile;
            if (p.address) form.address = p.address; // BD tiene prioridad o es más confiable
            if (p.district) form.district = p.district;
            if (p.province) form.province = p.province;
            if (p.department) form.department = p.department;

            // Campos específicos de miembro (si el formulario los soporta)
            if ('birth_date' in form && p.birth_date) form.birth_date = p.birth_date;
            if ('gender' in form && p.gender) form.gender = p.gender;
            if ('emergency_contact_name' in form && p.emergency_contact_name) form.emergency_contact_name = p.emergency_contact_name;
            if ('emergency_contact_phone' in form && p.emergency_contact_phone) form.emergency_contact_phone = p.emergency_contact_phone;
            if ('blood_type' in form && p.blood_type) form.blood_type = p.blood_type;
            if ('allergies' in form && p.allergies) form.allergies = p.allergies;
            if ('medical_notes' in form && p.medical_notes) form.medical_notes = p.medical_notes;
        }
    };

    return {
        handlePartnerFound
    };
}
