import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface BackupFile {
  filename: string;
  size: number;
  created_at?: string;
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

  restoreBackup(file: File): Observable<{ message: string }> {
    const formData = new FormData();
    formData.append('backup', file);
    return this.http.post<{ message: string }>(`${this.apiUrl}/backups/restore`, formData);
  }

  deleteBackup(filename: string): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/backups/${filename}`);
  }
}
