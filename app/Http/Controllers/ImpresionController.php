<?php

namespace App\Http\Controllers;

use App\Print\OrderItemPrint;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ImpresionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        $data = request()->all();

        $productos = [];

        $productosKeys = array_filter(array_keys($data), function($key) {
            return strpos($key, 'producto_') !== false;
        });

        foreach ($productosKeys as $key) {
            $producto = explode(',', $data[$key]);
            $productos[] = [
                'nombre' => $producto[0],
                'cantidad' => $producto[1],
                'total' => $producto[2],
                'es_dinamico' => (bool) $producto[3],
            ];
        }

        $pagos = [];
        
        $pagosKeys = array_filter(array_keys($data), function($key) {
            return strpos($key, 'pago_') !== false;
        });

        foreach ($pagosKeys as $key) {
            $tipo_pago = str_replace('pago_', '', $key);

            $pagos[] = [
                'tipo' => ucfirst(strtolower(($tipo_pago ?? 'pago'))),
                'valor' => $data[$key],
            ];
        }

        $nombreImpresora = "POS-58";
        $connector = new WindowsPrintConnector($nombreImpresora);
        $impresora = new Printer($connector);

        $impresora->setJustification(Printer::JUSTIFY_LEFT);
        $impresora->setFont(Printer::FONT_B);
        $impresora->setTextSize(2, 1);

        $impresora->text("JC Licores\n");

        $impresora->setTextSize(1, 1);
        $impresora->text("Andres Riascos                            \n");
        $impresora->text("Nit. 1084450150                           \n");
        $impresora->text("Calle 18 # 2 - 50                         \n");
        $impresora->text("Telefono +57 3157594115                   \n");
        $impresora->text("Regimen común                             \n");
        
        $impresora->feed(1);
        
        $impresora->text("Mesa: ".$data['mesa']."\n");
        $impresora->text("Id: #".$data['id']."\n");
        $impresora->text("Fecha: ".$data['fecha']."\n");
        $impresora->text("Mesero: ".$data['mesero']."\n");

        $impresora->text("------------------------------------------\n");
        $impresora->text("PRODUCTO x CANTIDAD                  TOTAL\n");
        $impresora->text("------------------------------------------\n");

        foreach ($productos as $product) {
            (new OrderItemPrint(
                $product['nombre'],
                $product['cantidad'],
                $product['total'],
                $impresora,
                !$product['es_dinamico']
            ))->print();
        }

        $impresora->text("------------------------------------------\n");

        $impresora->setTextSize(2, 1);

        (new OrderItemPrint(
            'SUBTOTAL',
            -1,
            $data['subtotal'],
            $impresora
        ))->print(21);


        if( isset($data['cambio']) || isset($data['pendiente']) ){
            $impresora->setTextSize(1, 1);

            $impresora->text("------------------------------------------\n");

            $impresora->setTextSize(2, 1);

            (new OrderItemPrint(
                isset($data['cambio']) ? 'CAMBIO' : 'PENDIENTE',
                -1,
                abs( isset($data['cambio']) ? $data['cambio'] : $data['pendiente'] ),
                $impresora
            ))->print(21);
        }

        $impresora->setTextSize(1, 1);

        if(count($pagos) > 0){
            $impresora->text("------------------------------------------\n");
            $impresora->text("TIPO DE PAGO                         VALOR\n");
            $impresora->text("------------------------------------------\n");
        }

        foreach ( $pagos as $payment ) {
            (new OrderItemPrint(
                $payment['tipo'],
                -1,
                $payment['valor'],
                $impresora
            ))->print();
        }

        $impresora->feed(3);

        $impresora->close();

        return "<script>window.close();</script>";
    }
}
