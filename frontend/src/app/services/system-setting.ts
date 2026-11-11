import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PaginatedResponse } from './pagination';

interface SystemSetting {
  id: number;
  key: string;
  value: string | null;
  type: string;
  group: string;
  description: string | null;
  is_public: boolean;
}

interface SettingsResponse {
  settings: SystemSetting[];
}

interface SettingResponse {
  setting: SystemSetting;
  message: string;
}

interface GetValueResponse {
  key: string;
  value: any;
}

interface PublicSettingsResponse {
  settings: Record<string, any>;
}

export interface SettingField {
  key: string;
  label: string;
  type: string;
  description: string | null;
  options: Record<string, string> | null;
  value: any;
  default: any;
  group: string;
}

export interface SettingChange {
  id: number;
  key: string;
  label: string;
  group: string | null;
  type: string;
  old_value: string | null;
  new_value: string | null;
  user: { id: number; name: string } | null;
  created_at: string;
}

export interface SettingGroup {
  key: string;
  label: string;
  description: string;
  can_edit: boolean;
  fields: SettingField[];
}

export interface MyPreferencesResponse {
  groups: SettingGroup[];
  preferences: Record<string, any>;
  message?: string;
}

interface SchemaResponse {
  groups: SettingGroup[];
}

interface BulkUpdateResponse {
  groups: SettingGroup[];
  message: string;
}

@Injectable({
  providedIn: 'root',
})
export class SystemSettingService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getSettings(params?: any): Observable<SettingsResponse> {
    return this.http.get<SettingsResponse>(`${this.apiUrl}/settings`, { params });
  }

  getSetting(id: number): Observable<SettingResponse> {
    return this.http.get<SettingResponse>(`${this.apiUrl}/settings/${id}`);
  }

  createSetting(setting: any): Observable<SettingResponse> {
    return this.http.post<SettingResponse>(`${this.apiUrl}/settings`, setting);
  }

  updateSetting(id: number, setting: any): Observable<SettingResponse> {
    return this.http.put<SettingResponse>(`${this.apiUrl}/settings/${id}`, setting);
  }

  deleteSetting(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/settings/${id}`);
  }

  getValue(key: string): Observable<GetValueResponse> {
    return this.http.get<GetValueResponse>(`${this.apiUrl}/settings/get`, { params: { key } });
  }

  setValue(key: string, value: any, options?: any): Observable<GetValueResponse> {
    return this.http.post<GetValueResponse>(`${this.apiUrl}/settings/set`, {
      key,
      value,
      ...options,
    });
  }

  getPublicSettings(): Observable<PublicSettingsResponse> {
    return this.http.get<PublicSettingsResponse>(`${this.apiUrl}/settings/public`);
  }

  getSchema(): Observable<SchemaResponse> {
    return this.http.get<SchemaResponse>(`${this.apiUrl}/settings/schema`);
  }

  bulkUpdate(values: Record<string, any>): Observable<BulkUpdateResponse> {
    return this.http.put<BulkUpdateResponse>(`${this.apiUrl}/settings/bulk`, { values });
  }

  resetGroup(group: string): Observable<BulkUpdateResponse> {
    return this.http.post<BulkUpdateResponse>(`${this.apiUrl}/settings/reset`, { group });
  }

  getHistory(page = 1, perPage = 10): Observable<PaginatedResponse<SettingChange>> {
    return this.http.get<PaginatedResponse<SettingChange>>(`${this.apiUrl}/settings/history`, {
      params: { page, per_page: perPage },
    });
  }

  uploadLogo(file: File): Observable<BulkUpdateResponse> {
    const formData = new FormData();
    formData.append('logo', file);
    return this.http.post<BulkUpdateResponse>(`${this.apiUrl}/settings/logo`, formData);
  }

  deleteLogo(): Observable<BulkUpdateResponse> {
    return this.http.delete<BulkUpdateResponse>(`${this.apiUrl}/settings/logo`);
  }

  getMyPreferences(): Observable<MyPreferencesResponse> {
    return this.http.get<MyPreferencesResponse>(`${this.apiUrl}/me/preferences`);
  }

  /** A null value restores the system default for that preference. */
  updateMyPreferences(preferences: Record<string, any>): Observable<MyPreferencesResponse> {
    return this.http.put<MyPreferencesResponse>(`${this.apiUrl}/me/preferences`, { preferences });
  }

  getSettingsByGroup(group: string): Observable<SettingsResponse> {
    return this.http.get<SettingsResponse>(`${this.apiUrl}/settings/group`, { params: { group } });
  }
}
