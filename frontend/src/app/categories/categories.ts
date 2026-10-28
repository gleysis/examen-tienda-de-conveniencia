import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CategoryService } from '../services/category';

interface Category {
  id: number;
  name: string;
  slug: string;
  description: string;
  is_active: boolean;
}

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-categories',
  styleUrl: './categories.css',
  templateUrl: './categories.html',
})
export class Categories implements OnInit {
  categories: Category[] = [];
  isLoading = false;
  errorMessage = '';
  showForm = false;
  isEditing = false;
  selectedCategory: Category | null = null;

  formData = {
    name: '',
    slug: '',
    description: '',
    is_active: true,
  };

  formErrors: any = {};

  constructor(private categoryService: CategoryService) {}

  ngOnInit(): void {
    this.loadCategories();
  }

  loadCategories(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.categoryService.getCategories().subscribe({
      next: (response) => {
        this.categories = response.categories;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading categories:', error);
        this.errorMessage = error.error?.message || 'Error al cargar categorías';
        this.isLoading = false;
      },
    });
  }

  openCreateForm(): void {
    this.showForm = true;
    this.isEditing = false;
    this.selectedCategory = null;
    this.formErrors = {};
    this.errorMessage = '';
    this.formData = {
      name: '',
      slug: '',
      description: '',
      is_active: true,
    };
  }

  openEditForm(category: Category): void {
    this.showForm = true;
    this.isEditing = true;
    this.selectedCategory = category;
    this.formErrors = {};
    this.errorMessage = '';
    this.formData = {
      name: category.name,
      slug: category.slug,
      description: category.description,
      is_active: category.is_active,
    };
  }

  closeForm(): void {
    this.showForm = false;
    this.selectedCategory = null;
  }

  onSubmit(): void {
    if (this.isEditing && this.selectedCategory) {
      this.updateCategory();
    } else {
      this.createCategory();
    }
  }

  createCategory(): void {
    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.categoryService.createCategory(this.formData).subscribe({
      next: () => {
        this.loadCategories();
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.errors) {
          this.formErrors = error.error.errors;
          this.errorMessage = 'Por favor corrige los errores del formulario';
        } else {
          this.errorMessage = error.error?.message || 'Error al crear categoría';
        }
      },
    });
  }

  updateCategory(): void {
    if (!this.selectedCategory) return;

    this.isLoading = true;
    this.formErrors = {};
    this.errorMessage = '';

    this.categoryService.updateCategory(this.selectedCategory.id, this.formData).subscribe({
      next: () => {
        this.loadCategories();
        this.closeForm();
        this.isLoading = false;
      },
      error: (error) => {
        this.isLoading = false;
        if (error.error && error.error.errors) {
          this.formErrors = error.error.errors;
          this.errorMessage = 'Por favor corrige los errores del formulario';
        } else {
          this.errorMessage = error.error?.message || 'Error al actualizar categoría';
        }
      },
    });
  }

  deleteCategory(category: Category): void {
    if (confirm(`¿Estás seguro de eliminar la categoría ${category.name}?`)) {
      this.isLoading = true;
      this.categoryService.deleteCategory(category.id).subscribe({
        next: () => {
          this.loadCategories();
          this.isLoading = false;
        },
        error: (error) => {
          this.errorMessage = 'Error al eliminar categoría';
          this.isLoading = false;
        },
      });
    }
  }

  onNameChange(): void {
    this.formData.slug = this.formData.name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
  }
}
