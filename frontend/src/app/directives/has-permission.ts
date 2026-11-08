import { Directive, Input, TemplateRef, ViewContainerRef, inject } from '@angular/core';
import { AuthService } from '../services/auth';

@Directive({
  selector: '[appHasPermission]',
})
export class HasPermission {
  private readonly templateRef = inject(TemplateRef);
  private readonly viewContainer = inject(ViewContainerRef);
  private readonly authService = inject(AuthService);
  private hasView = false;

  @Input() set appHasPermission(permissions: string | string[]) {
    const required = Array.isArray(permissions) ? permissions : [permissions];
    const allowed = this.authService.hasAnyPermission(required);

    if (allowed && !this.hasView) {
      this.viewContainer.createEmbeddedView(this.templateRef);
      this.hasView = true;
    } else if (!allowed && this.hasView) {
      this.viewContainer.clear();
      this.hasView = false;
    }
  }
}
