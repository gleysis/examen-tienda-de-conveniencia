import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface BackupFile {
  filename: string;
  size: number;
  created_at?: string;
}

export interface RestoreResponse {
  message: string;
  safety_backup?: string;
}

@Injectable({
  providedIn: 'root',
})
export class BackupService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  getBackups(): Observable<{ backups: BackupFile[] }> {
    return this.http.get<{ backups: BackupFile[] }>(`${this.apiUrl}/backups`);
  }

  createBackup(): Observable<{ backup: BackupFile; message: string }> {
    return this.http.post<{ backup: BackupFile; message: string }>(`${this.apiUrl}/backups`, {});
  }

  downloadBackup(filename: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/backups/${filename}`, { responseType: 'blob' });
  }

  restoreBackup(file: File): Observable<RestoreResponse> {
    const formData = new FormData();
    formData.append('backup', file);
    return this.http.post<RestoreResponse>(`${this.apiUrl}/backups/restore`, formData);
  }

  restoreStoredBackup(filename: string): Observable<RestoreResponse> {
    return this.http.post<RestoreResponse>(
      `${this.apiUrl}/backups/${encodeURIComponent(filename)}/restore`,
      {}
    );
  }

  deleteBackup(filename: string): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/backups/${filename}`);
  }
}
