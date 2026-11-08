import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth';
import { PreferencesService } from '../services/preferences';
import { MENU_ITEMS } from '../navigation';
import { APP_PIPES } from '../pipes';

@Component({
  imports: [CommonModule, ReactiveFormsModule, ...APP_PIPES],
  selector: 'app-login',
  styleUrl: './login.css',
  templateUrl: './login.html',
})
export class Login {
  private static readonly REMEMBERED_EMAIL_KEY = 'remembered_email';

  loginForm: FormGroup;
  isLoading = false;
  errorMessage = '';
  showPassword = false;

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private preferencesService: PreferencesService,
    private router: Router
  ) {
    const rememberedEmail = localStorage.getItem(Login.REMEMBERED_EMAIL_KEY) ?? '';
    this.loginForm = this.fb.group({
      email: [rememberedEmail, [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(6)]],
      remember: [rememberedEmail !== ''],
    });
  }

  togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMessage = '';

    const { email, password, remember } = this.loginForm.value;

    this.authService.login(email, password).subscribe({
      next: () => {
        this.isLoading = false;
        if (remember) {
          localStorage.setItem(Login.REMEMBERED_EMAIL_KEY, email);
        } else {
          localStorage.removeItem(Login.REMEMBERED_EMAIL_KEY);
        }
        this.preferencesService.load();
        const first = MENU_ITEMS.find((item) => this.authService.hasAnyPermission(item.permissions));
        this.router.navigate([first?.route ?? '/settings']);
      },
      error: (error) => {
        this.isLoading = false;
        const serverMessage = error.error?.errors?.email?.[0] ?? error.error?.errors?.password?.[0] ?? error.error?.message;
        if (error.status === 401 || error.status === 422) {
          this.errorMessage = serverMessage || 'Correo o contraseña incorrectos.';
          this.loginForm.get('password')?.reset();
        } else if (error.status === 429) {
          this.errorMessage = serverMessage || 'Demasiados intentos de inicio de sesión. Espera un momento e intenta de nuevo.';
        } else if (error.status === 0) {
          this.errorMessage = 'No se puede conectar con el servidor. Verifica tu conexión e intenta de nuevo.';
        } else {
          this.errorMessage = 'Ocurrió un error al iniciar sesión. Intenta nuevamente.';
        }
      },
    });
  }
}
