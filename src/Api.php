<?php
declare(strict_types=1);
function handleApi(Store $store):void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD']??'GET';
    try {
        if($path==='/api/payments/payfast/itn'&&$method==='POST') {
            if(getenv('PAYFAST_ENABLED')!=='1')throw new HttpError('Payment notifications are not enabled.',503);
            require_once __DIR__.'/Integrations.php';
            $raw=file_get_contents('php://input');if(strlen($raw)>65536)throw new HttpError('Notification too large.',413);
            $sandbox=getenv('PAYFAST_SANDBOX')!=='0';$provider=new \CRM\Integration\PayFast(getenv('PAYFAST_MERCHANT_ID')?:'',getenv('PAYFAST_MERCHANT_KEY')?:'',getenv('PAYFAST_PASSPHRASE')?:'',$sandbox,new \CRM\Integration\CurlTransport(['www.payfast.co.za','sandbox.payfast.co.za']));
            $fields=\CRM\Integration\PayFast::parseItn($raw);$id=(int)($fields['m_payment_id']??0);$order=$store->db->one('SELECT o.*,u.role FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.id=?',[$id]);if(!$order)throw new HttpError('Unknown order.',400);
            $event=$provider->validateItn($raw,$_SERVER['REMOTE_ADDR']??'',$order,\CRM\Integration\PayFast::trustedIps($sandbox));
            $out=$store->recordVerifiedPayment($event);echo json_encode($out,JSON_THROW_ON_ERROR);return;
        }
        $data=[];
        if($method==='POST'){$raw=file_get_contents('php://input');if(strlen($raw)>65536)throw new HttpError('Request too large.',413);$data=str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?json_decode($raw,true,512,JSON_THROW_ON_ERROR):$_POST;if(!is_array($data))throw new HttpError('Expected a JSON object.');$store->csrf($_SERVER['HTTP_X_CSRF_TOKEN']??$data['_csrf']??null);}
        if($method==='GET') {
            $out=match($path){
                '/api/session'=>$store->session(),'/api/products'=>$store->catalog($_GET),'/api/categories'=>['categories'=>$store->categories()],'/api/cart'=>$store->cart(),'/api/wishlist'=>$store->wishlist(),'/api/account'=>$store->account(),'/api/build/options'=>$store->buildOptions(),'/api/admin'=>$store->dashboard(),default=>null
            };
            if($out===null){
                if(preg_match('#^/api/products/([a-z0-9-]+)$#',$path,$m)){$out=$store->product($m[1]);if(!$out)throw new HttpError('Product not found.',404);}
                elseif(preg_match('#^/api/build/([a-f0-9]{32})$#',$path,$m))$out=$store->sharedBuild($m[1]);
                elseif(preg_match('#^/api/orders/(\d+)$#',$path,$m))$out=$store->order((int)$m[1]);
                elseif(preg_match('#^/api/support/(\d+)$#',$path,$m))$out=$store->ticket((int)$m[1]);
                elseif(preg_match('#^/api/quotes/(\d+)/pdf$#',$path,$m)){header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="CRM-quotation-'.(int)$m[1].'.pdf"');echo $store->quotePdf((int)$m[1]);return;}
                elseif($path==='/api/admin/export/orders'){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="crm-orders.csv"');echo $store->exportOrders();return;}
                else throw new HttpError('API route not found.',404);
            }
        } elseif($method==='POST') {
            $out=match($path){
                '/api/auth/register'=>$store->register($data),'/api/auth/login'=>$store->login($data),'/api/auth/logout'=>$store->logout(),'/api/auth/forgot'=>$store->forgot($data),'/api/auth/reset'=>$store->resetPassword($data),'/api/cart/items'=>$store->updateCart($data),'/api/cart/add'=>$store->updateCart($data,true),'/api/checkout'=>$store->checkout($data),'/api/dealers/apply'=>$store->applyDealer($data),'/api/wishlist'=>$store->toggleWishlist($data),'/api/build/validate'=>$store->validateBuild((array)($data['components']??[])),'/api/build/save'=>$store->saveBuild($data),'/api/build/cart'=>$store->buildCart($data),'/api/quotes'=>$store->createQuote($data),'/api/support'=>$store->createTicket($data),'/api/consent'=>$store->consent($data),'/api/assistant'=>$store->assistant($data),'/api/admin/products'=>$store->adminProduct($data),'/api/admin/variants'=>$store->adminVariant($data),'/api/admin/categories'=>$store->adminCategory($data),'/api/admin/coupons'=>$store->adminCoupon($data),'/api/account/profile'=>$store->updateProfile($data),'/api/account/addresses'=>$store->saveAddress($data),'/api/account/addresses/remove'=>$store->removeAddress($data),'/api/cart/coupon'=>$store->applyCoupon($data),'/api/admin/dealers'=>$store->adminDealer($data),'/api/admin/orders'=>$store->adminOrder($data),'/api/admin/settings'=>$store->adminSetting($data),'/api/admin/campaigns'=>$store->adminCampaign($data),'/api/admin/tickets'=>$store->adminTicket($data),default=>null
            };
            if($out===null){if(preg_match('#^/api/support/(\d+)/reply$#',$path,$m))$out=$store->replyTicket((int)$m[1],$data);else throw new HttpError('API route not found.',404);}
        } else {header('Allow: GET, POST');throw new HttpError('Method not allowed.',405);}
        echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }catch(HttpError $e){header('Content-Type: application/json; charset=utf-8');header_remove('Content-Disposition');http_response_code($e->status);echo json_encode(['error'=>$e->getMessage()]);}
    catch(\CRM\Integration\InvalidNotification $e){http_response_code(400);echo json_encode(['error'=>'Invalid payment notification.']);}
    catch(\CRM\Integration\ProviderUnavailable $e){http_response_code(503);echo json_encode(['error'=>'Provider temporarily unavailable.']);}
    catch(JsonException $e){http_response_code(400);echo json_encode(['error'=>'Invalid JSON request.']);}
    catch(Throwable $e){error_log('CRM API: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'The request could not be completed. Please try again.']);}
}
