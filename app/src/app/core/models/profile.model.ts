export interface ProfileUser {
  id: string | number;
  name: string;
  email: string;
  avatar_url?: string | null;
  two_factor_enabled?: boolean;
}

export interface ProfileResponse {
  data: ProfileUser;
}

export interface UpdateProfilePayload {
  name: string;
}

export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ProfileImageResponse {
  data: {
    avatar_url?: string | null;
  };
}
