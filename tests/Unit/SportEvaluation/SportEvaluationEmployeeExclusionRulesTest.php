<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationEmployeeExclusionRules;
use Tests\TestCase;

final class SportEvaluationEmployeeExclusionRulesTest extends TestCase
{
    private SportEvaluationEmployeeExclusionRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new SportEvaluationEmployeeExclusionRules;
    }

    public function test_excludes_politeknik_sinarmas_company_variants(): void
    {
        $this->assertTrue($this->rules->isExcludedCompany('Politeknik Sinar Mas Berau Coal'));
        $this->assertTrue($this->rules->isExcludedCompany('Politeknik Sinarmas Berau Coal'));
        $this->assertTrue($this->rules->isExcludedCompany('Politeknik Sinarmas'));
        $this->assertTrue($this->rules->isExcludedCompany('POLITEKNIK SINAR MAS'));
        $this->assertTrue($this->rules->isExcludedCompany('PT Politeknik Sinar Mas Berau Coal'));
    }

    public function test_excludes_sinarmas_maritime_company_variants(): void
    {
        $this->assertTrue($this->rules->isExcludedCompany('PT Sinarmas LDA Maritime'));
        $this->assertTrue($this->rules->isExcludedCompany('Sinarmas Maritim'));
        $this->assertTrue($this->rules->isExcludedCompany('Sinarmas Maritin'));
        $this->assertTrue($this->rules->isExcludedCompany('PT Sinar Mas LDA Maritime'));
    }

    public function test_excludes_fusi_company_variants(): void
    {
        $this->assertTrue($this->rules->isExcludedCompany('Fusi'));
        $this->assertTrue($this->rules->isExcludedCompany('PT Fusi'));
        $this->assertTrue($this->rules->isExcludedCompany('PT Fusi Solusi Transformasi'));
        $this->assertTrue($this->rules->isExcludedCompany('Fusi Solusi Transformasi'));
    }

    public function test_excludes_yayasan_dharma_bakti_company_variants(): void
    {
        $this->assertTrue($this->rules->isExcludedCompany('Yayasan Dharma Bakti'));
        $this->assertTrue($this->rules->isExcludedCompany('Yayasan Dharma Bakti Berau Coal'));
        $this->assertTrue($this->rules->isExcludedCompany('YAYASAN DHARMA BAKTI'));
        $this->assertTrue($this->rules->isExcludedCompany('Yayasan Dharma-Bakti Berau Coal'));
    }

    public function test_does_not_exclude_other_companies(): void
    {
        $this->assertFalse($this->rules->isExcludedCompany('PT Berau Coal'));
        $this->assertFalse($this->rules->isExcludedCompany('PT Pamapersada Nusantara'));
        $this->assertFalse($this->rules->isExcludedCompany(null));
        $this->assertFalse($this->rules->isExcludedCompany(''));
    }

    public function test_excluded_row_honors_company_keys(): void
    {
        $this->assertTrue($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Operator',
            'site' => 'BMO',
            'nama' => 'Andi',
            'company' => 'Politeknik Sinarmas',
        ]));

        $this->assertTrue($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Staff',
            'site' => 'LMO',
            'nama' => 'Budi',
            'nama_perusahaan' => 'PT Sinarmas LDA Maritime',
        ]));

        $this->assertTrue($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Staff',
            'site' => 'GMO',
            'nama' => 'Cici',
            'company' => 'PT Fusi Solusi Transformasi',
        ]));

        $this->assertTrue($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Staff',
            'site' => 'BMO',
            'nama' => 'Dewi',
            'company' => 'Yayasan Dharma Bakti Berau Coal',
        ]));

        $this->assertFalse($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Operator',
            'site' => 'BMO',
            'nama' => 'Andi',
            'company' => 'PT Berau Coal',
        ]));
    }

    public function test_excludes_berau_coal_intern_departments(): void
    {
        $this->assertTrue($this->rules->isExcludedBerauInternDepartment('PT Berau Coal', 'Internship'));
        $this->assertTrue($this->rules->isExcludedBerauInternDepartment('PT Berau Coal', 'Dept Poltek'));
        $this->assertTrue($this->rules->isExcludedBerauInternDepartment('PT Berau Coal', 'Kampus Merdeka'));
        $this->assertTrue($this->rules->isExcludedBerauInternDepartment('Berau Coal', 'Prakerin Site'));
        $this->assertTrue($this->rules->isExcludedBerauInternDepartment('PT. Berau Coal', 'KAMPUS-MERDEKA'));

        $this->assertFalse($this->rules->isExcludedBerauInternDepartment('PT Berau Coal', 'Mining Operation'));
        $this->assertFalse($this->rules->isExcludedBerauInternDepartment('PT Pamapersada Nusantara', 'Internship'));
        $this->assertFalse($this->rules->isExcludedBerauInternDepartment('Yayasan Dharma Bakti Berau Coal', 'Poltek'));
        $this->assertFalse($this->rules->isExcludedBerauInternDepartment('PT Berau Coal', null));
        $this->assertFalse($this->rules->isExcludedBerauInternDepartment(null, 'Internship'));
    }

    public function test_excluded_row_honors_berau_intern_department(): void
    {
        $this->assertTrue($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Intern',
            'site' => 'BMO',
            'nama' => 'Andi',
            'company' => 'PT Berau Coal',
            'departement' => 'Internship',
        ]));

        $this->assertFalse($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Operator',
            'site' => 'BMO',
            'nama' => 'Budi',
            'company' => 'PT Berau Coal',
            'departement' => 'Production',
        ]));
    }

    public function test_berau_intern_sql_predicate(): void
    {
        [$sql, $bindings] = $this->rules->berauInternDepartmentNotExcludedPredicate('e');

        $this->assertStringContainsString('departement', $sql);
        $this->assertStringContainsString('NOT (', $sql);
        $this->assertSame([
            'PTBERAUCOAL',
            'BERAUCOAL',
            '%INTERNSHIP%',
            '%POLTEK%',
            '%KAMPUS%MERDEKA%',
            '%PRAKERIN%',
        ], $bindings);
    }

    public function test_active_stats_employee_predicate_includes_company_and_berau(): void
    {
        [$sql, $bindings] = $this->rules->activeStatsEmployeeNotExcludedPredicate('e');

        $this->assertStringContainsString('POLITEKNIKSINARMAS', implode(',', $bindings));
        $this->assertStringContainsString('YAYASANDHARMABAKTI', implode(',', $bindings));
        $this->assertStringContainsString('PTBERAUCOAL', implode(',', $bindings));
        $this->assertStringContainsString('INTERNSHIP', implode(',', $bindings));
        $this->assertStringContainsString('AND', $sql);
    }

    public function test_company_sql_predicate_covers_all_excluded_companies(): void
    {
        [$sql, $bindings] = $this->rules->companyNotExcludedPredicate('e');

        $this->assertStringContainsString('nama_perusahaan', $sql);
        $this->assertStringContainsString('NOT (', $sql);
        $this->assertSame([
            '%POLITEKNIKSINARMAS%',
            '%SINARMAS%MARITIM%',
            '%SINARMAS%MARITIN%',
            'FUSI%',
            'PTFUSI%',
            '%YAYASANDHARMABAKTI%',
        ], $bindings);
    }
}
