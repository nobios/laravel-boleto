<?php
/**
 *   Copyright (c) 2016 Eduardo Gusmão
 *
 *   Permission is hereby granted, free of charge, to any person obtaining a
 *   copy of this software and associated documentation files (the "Software"),
 *   to deal in the Software without restriction, including without limitation
 *   the rights to use, copy, modify, merge, publish, distribute, sublicense,
 *   and/or sell copies of the Software, and to permit persons to whom the
 *   Software is furnished to do so, subject to the following conditions:
 *
 *   The above copyright notice and this permission notice shall be included in all
 *   copies or substantial portions of the Software.
 *
 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 *   INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 *   PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *   COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 *   WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
 *   IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Eduardokum\LaravelBoleto\Boleto\Banco;

use Eduardokum\LaravelBoleto\Boleto\AbstractBoleto;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Util;

class Bb extends AbstractBoleto implements BoletoContract
{
    /**
     * Código do banco
     * @var string
     */
    protected $codigoBanco = self::COD_BANCO_BB;
    /**
     * Define as carteiras disponíveis para este banco
     * @var array
     */
    protected $carteiras = array('11','12','15','16','17','18','31','51');
    /**
     * Espécie do documento, coódigo para remessa
     * @var string
     */
    protected $especiesCodigo = [
        'DM' => '01',
        'NP' => '02',
        'NS' => '03',
        'REC' => '05',
        'LC' => '08',
        'W' => '09',
        'CH' => '10',
        'DS' => '12',
        'ND' => '13',
    ];
    /**
     * Define o número do convênio (4, 6 ou 7 caracteres)
     * @var string
     */
    protected $convenio;
    /**
     * Defgine o numero da variação da carteira.
     * @var string
     */
    protected $variacao_carteira;
    /**
     * Define o número do convênio. Sempre use string pois a quantidade de caracteres é validada.
     *
     * @param string $convenio
     * @return BancoDoBrasil
     */
    public function setConvenio($convenio)
    {
        $this->convenio = $convenio;
        return $this;
    }
    /**
     * Retorna o número do convênio
     *
     * @return string
     */
    public function getConvenio()
    {
        return $this->convenio;
    }
    /**
     * Define o número da variação da carteira, para saber quando utilizar o nosso numero de 17 posições.
     *
     * @param string $variacao_carteira
     * @return BancoDoBrasil
     */
    public function setVariacaoCarteira($variacao_carteira)
    {
        $this->variacao_carteira = (int) $variacao_carteira;
        return $this;
    }
    /**
     * Retorna o número da variacao de carteira
     *
     * @return string
     */
    public function getVariacaoCarteira()
    {
        return $this->variacao_carteira;
    }
    /**
     * Método que valida se o banco tem todos os campos obrigadotorios preenchidos
     */
    public function isValid()
    {
        if(
            empty($this->numero) ||
            empty($this->convenio) ||
            empty($this->carteira)
        )
        {
            return false;
        }
        return true;
    }
    /**
     * Gera o Nosso Número.
     *
     * @throws \Exception
     * @return string
     */
    protected function gerarNossoNumero()
    {
        $convenio = $this->getConvenio();
        $numero_boleto = $this->getNumero();
        $numero = null;
        switch (strlen($convenio)) {
            // Convênio de 4 dígitos, são 11 dígitos no nosso número
            case 4:
                $numero = Util::numberFormatGeral($convenio, 4) . Util::numberFormatGeral($numero_boleto, 7);
                break;
            // Convênio de 6 dígitos, são 11 dígitos no nosso número
            case 6:
                // Exceto no caso de ter a carteira sem registro (16 e 18) e a vartiação da carteira ser 17
                if (in_array($this->getCarteira(),['16','18']) and $this->getVariacaoCarteira() == 17) {
                    $numero = Util::numberFormatGeral($numero_boleto, 17);
                } else {
                    $numero = Util::numberFormatGeral($convenio, 6) . Util::numberFormatGeral($numero_boleto, 5);
                }
                break;
            // Convênio de 7 dígitos, são 17 dígitos no nosso número
            case 7:
                $numero = Util::numberFormatGeral($convenio, 7) . Util::numberFormatGeral($numero_boleto, 10);
                break;
            // Não é com 4, 6 ou 7 dígitos? Não existe.
            default:
                throw new \Exception('O código do convênio precisa ter 4, 6 ou 7 dígitos!');
        }
        return $numero;
    }
    /**
     * Método que retorna o nosso numero usado no boleto. alguns bancos possuem algumas diferenças.
     *
     * @return string
     */
    public function getNossoNumeroBoleto()
    {
        $nn = $this->getNossoNumero();
        return strlen($nn) < 17 ? $nn . '-' . Util::modulo11($nn) : $nn;
    }
    /**
     * Método para gerar o código da posição de 20 a 44
     *
     * @return string
     * @throws \Exception
     */
    protected function getCampoLivre()
    {
        if ($this->campoLivre) {
            return $this->campoLivre;
        }
        $length = strlen($this->getConvenio());
        $nossoNumero = $this->gerarNossoNumero();
        // Apenas para convênio com 6 dígitos, modalidade sem registro - carteira 16 e 18 (definida para 21)
        if (strlen($this->getNumero()) > 10) {
            if ($length == 6 and in_array($this->getCarteira(),['16','18']) and Util::numberFormatGeral($this->getVariacaoCarteira(), 3) == '017') {
                // Convênio (6) + Nosso número (17) + Carteira (2)
                return $this->campoLivre = Util::numberFormatGeral($this->getConvenio(), 6) . $nossoNumero . '21';
            } else {
                throw new \Exception('Só é possível criar um boleto com mais de 10 dígitos no nosso número quando a carteira é 21 e o convênio possuir 6 dígitos.');
            }
        }
        switch ($length) {
            case 4:
            case 6:
                // Nosso número (11) + Agencia (4) + Conta (8) + Carteira (2)
                return $this->campoLivre =  $nossoNumero . Util::numberFormatGeral($this->getAgencia(), 4) . Util::numberFormatGeral($this->getConta(), 8) . Util::numberFormatGeral($this->getCarteira(), 2);
            case 7:
                // Zeros (6) + Nosso número (17) + Carteira (2)
                return $this->campoLivre =  '000000' . $nossoNumero . Util::numberFormatGeral($this->getCarteira(), 2);
        }
        throw new \Exception('O código do convênio precisa ter 4, 6 ou 7 dígitos!');
    }
}