<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Log;

class WebhookController extends Controller
{
    public function evenHandle(Request $request)
    {
        try{
            $payload = $request->all();
    
            if (!isset($payload['data']['pessoa']['nome'], $payload['data']['pessoa']['celular'], $payload['type']['id'])) {
                return response()->json(['message' => 'Dados insuficientes no webhook'], 400);
            }
    
            $token = $payload['token'];
            $numberFrom = $payload['numero'];
            $tag = $payload['etiqueta'];
    
            $nome = $payload['data']['pessoa']['nome'];
            $telefone = $payload['data']['pessoa']['celular'];
            $eventoTitulo = $payload['data']['evento']['titulo'] ?? 'evento';
            $eventoTipo = $payload['type']['id'];
    
            $mensagem = $this->getMensagemPorTipo(
                tipo: $eventoTipo,
                nome: $nome,
                evento: $eventoTitulo
            );
    
            if (!$mensagem) {
                return response()->json(
                    status: 200,
                    data: [
                        'message' => 'Evento ignorado'
                    ]
                );
            }
    
            if($eventoTipo === 2){
                $result = $this->adicionarAoCrm(
                    dadosPessoa: $payload['data']['pessoa'],
                    token: $token,
                    evento: $eventoTitulo,
                    tag: $tag
                );

                if(isset($result['error']) && $result['error']){
                    $updateResult = $this->updateContactTag(
                        numphoneber: $telefone,
                        token: $token,
                        tag: $tag
                    );
                }
            }
        
            $result =  $this->enviarMensagem(
                telefone: $telefone,
                mensagem: $mensagem,
                token: $token,
                numberFrom: $numberFrom
            );
    
            return response()->json(['message' => 'Webhook processado com sucesso']);
        }catch(Exception $error){
            Log::error($error->getMessage());
            return response()->json(
                status: 500,
                data: [
                    'message' => 'Ocorreu um erro durante o processamento do webhook: '. $error->getMessage()
                ],
            );
        }
    }

    private function getMensagemPorTipo($tipo, $nome, $evento)
    {
        return match ((int) $tipo) {
            2 => "Olá, $nome! Vimos que a sua inscrição no evento $evento foi confirmada com sucesso! Agora é só aguardar o grande dia chegar.\n\nPara saber mais detalhes sobre o evento e a sua inscrição, digite Confirmado ou confirmada que eu te conto tudo, até mais!",
            3 => "Olá, $nome! Recebemos o seu pedido de cancelamento da inscrição no evento $evento. Lamentamos que não poderá participar desta vez.\n\nCaso mude de ideia ou precise de ajuda para se inscrever em outro evento, é só me avisar! Estamos à disposição. Mas se você quiser conversar com os nossos atendentes e organizadores, digite Atendimento",
            7 => "Olá, $nome! Vimos que você efetuou o check-in no evento $evento. Que incrível!\n\nTemos várias atividades acontecendo durante o evento. Gostaria de receber atualizações sobre as melhores informações até o término? Então, digite a palavra CHEGUEI abaixo e aproveite o evento. Só digitar CHEGUEI",
            12 => "Olá, $nome! Informamos que o estorno da sua compra foi processado com sucesso. O valor será devolvido conforme as políticas do seu método de pagamento.\n\nCaso tenha alguma dúvida ou precise de ajuda para outras compras, é só me avisar! Estamos aqui para ajudar. Digite Ajuda que te transferimos para o atendimento sobre dúvidas.",
            default => null
        };
    }

    private function enviarMensagem($telefone, $mensagem, $token, $numberFrom)
    {
        $payload = [
            'from' => $numberFrom,
            'to' => $telefone,
            'body' => [
                'text' => $mensagem
            ]
        ];
        
        $response = Http::withToken($token)
            ->post('https://api.wts.chat/chat/v1/message/send', $payload);

        return $response->json();
    }

    private function adicionarAoCrm($dadosPessoa, $token, $evento, $tag)
    {
        $payload = [
            'phoneNumber' => $dadosPessoa['celular'],
            'name' => $dadosPessoa['nome'],
            'email' => $dadosPessoa['email'] ?? null,
            'tagNames' => [$tag]
        ];

        $response = Http::withToken($token)
            ->post('https://api.wts.chat/core/v1/contact', $payload);
        return $response->json();
    }

    private function updateContactTag($numphoneber, $token, $tag)
    {
        $response = Http::withToken($token)
            ->post("https://api.wts.chat/core/v1/contact/phonenumber/{$numphoneber}/tags", [
            'tagNames' => [$tag],
            'operation' => 'InsertIfNotExists'
        ]);
    
        return $response->json();
    }    

}
