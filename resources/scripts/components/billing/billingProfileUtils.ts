import { BillingProfile } from '@/api/account/billing';

export const emptyBillingProfile: BillingProfile = {
    legalName: '',
    companyName: null,
    email: '',
    phone: null,
    addressLine1: null,
    addressLine2: null,
    city: null,
    state: null,
    postcode: null,
    countryCode: 'MY',
    taxId: null,
    isBusiness: false,
};

export const billingProfileFieldLabels: Record<keyof BillingProfile, string> = {
    legalName: 'Legal Name',
    companyName: 'Company Name',
    email: 'Invoice Email',
    phone: 'Phone',
    addressLine1: 'Address Line 1',
    addressLine2: 'Address Line 2',
    city: 'City',
    state: 'State',
    postcode: 'Postcode',
    countryCode: 'Country Code',
    taxId: 'Tax ID',
    isBusiness: 'Business Billing Entity',
};

export const requiredBillingProfileFields: Array<keyof BillingProfile> = [
    'legalName',
    'email',
    'phone',
    'addressLine1',
    'city',
    'postcode',
    'countryCode',
];

export const normalizeBillingProfile = (profile: BillingProfile): BillingProfile => ({
    ...profile,
    legalName: profile.legalName.trim(),
    companyName: profile.companyName?.trim() || null,
    email: profile.email.trim(),
    phone: profile.phone?.trim() || null,
    addressLine1: profile.addressLine1?.trim() || null,
    addressLine2: profile.addressLine2?.trim() || null,
    city: profile.city?.trim() || null,
    state: profile.state?.trim() || null,
    postcode: profile.postcode?.trim() || null,
    countryCode: profile.countryCode.trim().toUpperCase() || 'MY',
    taxId: profile.taxId?.trim() || null,
    isBusiness: Boolean(profile.isBusiness),
});

export const getMissingBillingProfileFields = (profile: BillingProfile): Array<keyof BillingProfile> => {
    const normalized = normalizeBillingProfile(profile);

    return requiredBillingProfileFields.filter((field) => {
        const value = normalized[field];

        if (typeof value === 'string') {
            return value.trim().length < 1;
        }

        return !value;
    });
};

export const getMissingBillingProfileLabels = (profile: BillingProfile): string =>
    getMissingBillingProfileFields(profile)
        .map((field) => billingProfileFieldLabels[field])
        .join(', ');

export const isBillingProfileComplete = (profile: BillingProfile): boolean =>
    getMissingBillingProfileFields(profile).length < 1;
