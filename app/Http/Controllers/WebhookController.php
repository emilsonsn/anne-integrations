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
            Log::info("Webhook recebido!");

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

                Log::info("Tentando cadastrar contato para $nome");

                if(isset($result['error']) && $result['error']){
                    $updateResult = $this->updateContactTag(
                        numphoneber: $telefone,
                        token: $token,
                        tag: $tag
                    );
                    Log::info("Tag atualizada para para $nome");
                }
            }
        
            $result =  $this->enviarMensagem(
                telefone: $telefone,
                mensagem: $mensagem,
                token: $token,
                numberFrom: $numberFrom
            );

            Log::info("Mensagem enviada para $nome");
    
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

    private function getMensagemPorTipo($tipo, $nome, $evento): string|null
    {
        return match ((int) $tipo) {
            2 => "✅ Inscrição Confirmada\nOlá, $nome! Vimos que a sua inscrição no evento $evento foi confirmada com sucesso! Agora é só aguardar o grande dia e horário chegar para acontecer.\nPara saber mais detalhes sobre o evento e a sua inscrição, digite apenas o numeral *2* que eu te atualizamos tudo sobre o evento!",
            3 => "❌ Inscrição Cancelada\nOlá, $nome! Recebemos o seu pedido de cancelamento da inscrição no evento $evento. Lamentamos que não poderá participar desta vez.\nCaso mude de ideia ou precise de ajuda para se inscrever em outro evento, é só me avisar! Para falar com nossos atendentes, digite o numeral *3*",
            7 => "📍 Check-in Realizado\nOlá, $nome! Vimos que você efetuou o check-in no evento $evento. Que incrível!\nTemos várias atividades acontecendo. Quer receber atualizações durante o evento? Digite o numeral *1*",
            12 => "💸 Compra Estornada\nOlá, $nome! O estorno da sua compra no evento $evento foi processado com sucesso.\nCaso tenha dúvidas ou precise de ajuda com outras compras, é só me avisar. Digite o numeral *4* e te redirecionamos para o atendimento.",
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
