<?php

namespace Tests\Unit;

use App\Support\TextoPdf;
use PHPUnit\Framework\TestCase;

class TextoPdfTest extends TestCase
{
    public function test_gera_pdf_valido_com_acentos(): void
    {
        $pdf = TextoPdf::de('Lista de Usuários', "1. Ana\n2. José\n3. Açúcar");

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('Lista de Usu', $pdf);
    }

    public function test_remove_markdown_do_conteudo_do_pdf(): void
    {
        $limpo = TextoPdf::limparMarkdown(
            "1. **Acompanhamento diário**\n- **Tipo:** Transferência\n- [Entrar no Meet](https://meet.google.com/abc)",
        );

        $this->assertStringNotContainsString('**', $limpo);
        $this->assertStringContainsString('Acompanhamento diário', $limpo);
        $this->assertStringContainsString('Tipo: Transferência', $limpo);
        $this->assertStringContainsString('Entrar no Meet (https://meet.google.com/abc)', $limpo);

        $pdf = TextoPdf::de('Reuniões de hoje', $limpo);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringNotContainsString('**', $pdf);
    }
}
