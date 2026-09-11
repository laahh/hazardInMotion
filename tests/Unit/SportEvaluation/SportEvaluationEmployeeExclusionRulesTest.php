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

    public function test_does_not_exclude_other_companies(): void
    {
        $this->assertFalse($this->rules->isExcludedCompany('PT Berau Coal'));
        $this->assertFalse($this->rules->isExcludedCompany('Yayasan Dharma Bakti Berau Coal'));
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

        $this->assertFalse($this->rules->isExcludedRow([
            'jabatan_fungsional' => 'Operator',
            'site' => 'BMO',
            'nama' => 'Andi',
            'company' => 'PT Berau Coal',
        ]));
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
        ], $bindings);
    }
}
