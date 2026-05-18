export interface TwoFactorStatusResponse {
  data: {
    enabled: boolean;
    has_recovery_codes?: boolean;
  };
}

export interface TwoFactorSetupResponse {
  data: {
    secret: string;
    qr_code_url: string;
  };
}

export interface TwoFactorValidateResponse {
  data: {
    recovery_codes: string[];
  };
}

export interface TwoFactorRegenerateResponse {
  data: {
    recovery_codes: string[];
  };
}
