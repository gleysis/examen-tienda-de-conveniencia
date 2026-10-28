import { TestBed } from '@angular/core/testing';
import { Sale } from './sale';

describe('Sale', () => {
  let service: Sale;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Sale);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
