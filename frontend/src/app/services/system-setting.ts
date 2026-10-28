import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

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

  getSettingsByGroup(group: string): Observable<SettingsResponse> {
    return this.http.get<SettingsResponse>(`${this.apiUrl}/settings/group`, { params: { group } });
  }
}
