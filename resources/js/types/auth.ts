export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthRole = {
    id: number;
    name: string;
    slug: string;
    is_system: boolean;
};

export type Auth = {
    user: User | null;
    is_developer: boolean;
    roles: AuthRole[];
    permissions: string[];
    restricted_permissions?: string[];
};
