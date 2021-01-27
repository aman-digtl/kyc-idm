<?php

namespace DigtlCo\KycIdm;

use DigtlCo\KycIdm\Models\KycLog;
use DigtlCo\KycIdm\Models\KycUser;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * handles IDM processes
 */
class KycIdm
{

    public static function Hello($message)
    {
        return 'You called the hello method : ' . $message;
    }

    /**
     * accepts the kyc form request from an exernal form
     * @param Http\Request $request Request object from Http\Request containing the required data for KYC. parameters (country,email,firstName,middleInitial,lastName,dob,email,phone,documentTitle,country,file-front,file-back,file-face)
     * @param Int   $userID ID of user record to reference on this KYC result
     * @return immediate result of KYC process from IDM (possible to receive a PENDING result)
     */
    public static function HandleKycForm(Request $request, $userID)
    {
        $result = self::createIDM($request, $userID);
        return $result;
    }

    /**
     * send user info (along with the image files) to IDM for kyc/aml check
     */
    private static function createIDM(Request $request, $userID)
    {
        $endpoint = 'im/account/consumer';
        $params = [
            'bco' => $request->get('country'),
            'man' => $request->get('email'),
            'bfn' => $request->get('firstName'),
            'bmn' => $request->get('middleInitial'),
            'bln' => $request->get('lastName'),
            'dob' => $request->get('dob'),
            'tea' => $request->get('email'),
            'phn' => $request->get('phone'),
            'docType' => $request->get('documentTitle'),
            'docCountry' => $request->get('country'),
            'scanData' => self::encodeImage($request, 'file-front'),
            'backsideImageData' => self::encodeImage($request, 'file-back'),
            'faceImages' => [self::encodeImage($request, 'file-face')],
        ];

        // get result from IDM
        $result = self::callIDM($endpoint, 'POST', $params);
        
        //create a new model/record with the given userID and this result
        $user = KycUser::firstOrCreate(['user_id' => Auth::user()->id]);
        $user->idm_id = $result['tid'];
        $user->kyc_status = $result['state'];
        $user->save();

        // Log the operation
        self::logIdmAction($user->id, $result['state'], json_encode($result));

        switch ($result['state']) {
            case 'R': // under manual review
                $message = 'Your KYC is being reviewed.';
            break;
            case 'D': // rejected
                $message = 'Your KYC has been rejected.';
            break;
            case 'A': // accepted
                // RewardController::VerifyKyc($user);
                $message = 'Your KYC has been approved.';
            break;
            default: // unknown response
                $message = 'A system issue has occured.';
        }

        return [ 'status' => $result['state'], 'message' => $message ];
    }

    /**
     * Make http calls to IDM api
     * @param String $endpoint Endpoint url to be appended to IDM's api base url
     * @param String $verb [GET|POST|PUT]
     * @param Array $params Array of parameters to be passed to IDM
     * @param Boolean $isUpload Flag to indicate if this is an upload call or not (defaults to false)
     */
    private static function callIDM($endpoint, $verb = 'GET', $params, $isUpload = false)
    {

        $url = config('services.idm.endpoint') . $endpoint;
        $client = new Client([
            'base_uri' => $url,
            'timeout' => 120.0,
        ]);

        if ($isUpload) {
            $response = $client->request($verb, $url, [
                'multipart' => [
                    'name' => 'file',
                    'contents' => $params, //allow single file upload
                ],
                'auth' => [config('services.idm.user'), config('services.idm.key')],
            ]);
        } else {
            $response = $client->request($verb, $url, [
                'json' => $params,
                'auth' => [config('services.idm.user'), config('services.idm.key')],
            ]);
        }

        if ($response->getStatusCode() != 200) {
            echo $response->getReasonPhrase();
            return false;
        }

        return json_decode($response->getBody()->getContents(), 1);
    }

    /**
     * Encode an image into Base64
     */
    private static function encodeImage($request, $name)
    {
        return base64_encode(file_get_contents($request->file($name)));
    }

    // for handling webhook requests
    /**
     * main entry point of the webhook call
     */
    public static function AcceptWebhook(Request $request)
    {

        self::handleCallback($request);

        $data = ['response' => 'OK'];
        $data = json_encode($data);

        //create response required by IDM
        return response($data, 200)
            ->header('Access-Control-Allow-Origin', 'https://regtech.identitymind.store')
            ->header('Access-Control-Allow-Methods', 'POST')
            ->header('Access-Control-Allow-Headers', 'Content-Type')
            ->header('Content-Type', 'application/json');
    }

    /**
     * IDM callback for KYC updates.
     */
    private static function handleCallback(Request $request)
    {
        $data = $request->all();

        // verify state (and request shape)
        if (!isset($data['tid']) || !isset($data['state']) || !in_array($data['state'], ['D', 'A'])) {
            return response()->json(['response' => 'Invalid request body'], 400);
        }

        try {
            $user = KycUser::where('idm_id', $data['tid'])->firstOrFail();
            $user->kyc_status = $data['state'];
            $user->save();
            self::logIdmAction($user->id, $data['state'], $data);

            // RewardController::VerifyKyc($user);
        } catch (ModelNotFoundException $e) {
            return response()->json(['response' => 'User not found'], 404);
        }
    }

    // /**
    //  * specific codes for handling the response once verified
    //  * probably: parse and store data?
    //  */
    // private function handleResponse(Request $request)
    // {
    //     $token = $request->get('jwtresponse');
    //     $parsed = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))), 1);

    //     // These are some of the data we can get from the webhook request after  decoding ^^^
    //     $status = $parsed["state"];
    //     // $tid = $parsed["tid"];
    //     // $result = $parsed["kyc_result"];
    //     // $info = $parsed['form_data'];
    //     // 'full_name' => $info['full_name'],
    //     // 'last_name' => $info['last_name'],
    //     // 'email' => $info['email'],
    //     // 'phone_code' => $info['phone_code'],
    //     // 'phone' => $info['phone'],
    //     // 'country' => $info['country'],
    //     // 'street' => $info['street'],
    //     // 'city' => $info['city'],
    //     // 'state' => $info['state'],
    //     return $status;
    // }

    /**
     * Create a DB log entry for every action on the IDM API.
     */
    private static function logIdmAction ($user_id, $status, $raw) 
    {
        $log = new KycLog();
        $log->user = $user_id;
        $log->status = $status;
        $log->raw = json_encode($raw);
        $log->save();
    }
}
