<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    /**
     * Muestra los Términos y Condiciones del Servicio PuntoStock SaaS.
     */
    public function terms(): View
    {
        return view('legal.terms');
    }

    /**
     * Muestra la Política de Tratamiento de Datos Personales y Cookies Técnicas.
     */
    public function privacy(): View
    {
        return view('legal.privacy');
    }
}
