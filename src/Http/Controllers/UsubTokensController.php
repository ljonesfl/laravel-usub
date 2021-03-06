<?php

namespace Usub\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Usub\Core\UsubService;
use Usub\Core\UsubTokenRepository;
use Usub\Models\UsubToken;
use Illuminate\Routing\Controller as BaseController;

class UsubTokensController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $usubService;

    /**
     * UsubTokensController constructor.
     */
    public function __construct()
    {
        $this->usubService = new UsubService(
            new UsubTokenRepository( new UsubToken() )
        );

        $this->middleware('web');
        $this->middleware('auth');
        $this->middleware('usub_sign_in')->only( 'signIn' );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function signIn( Request $request )
    {
        $validator = Validator::make($request->all(), [
            'user2'                   => 'required|integer',
            'redirect_to_on_sign_in'  => 'nullable|string',
            'redirect_to_on_sign_out' => 'nullable|string'
        ]);

        if( $validator->fails() )
        {
            $errorMessage = __METHOD__ . '. ';

            $messagesBag = $validator->getMessageBag()->getMessages();

            if( $messagesBag )
				{
					foreach ($messagesBag as $messages)
					{
						foreach ($messages as $message)
						{
							$errorMessage .= $message. ' ';
						}
					}
				}

            Log::error( $errorMessage );

            throw new \Exception( $errorMessage );
        }

        $user1 = Auth::id();
        $user2 = $request->get('user2');

        $redirectToOnSignIn  = $request->get('redirect_to_on_sign_in')
            ?? Config::get( 'usub.redirect_to_on_sign_in' );

        $redirectToOnSignOut = $request->get('redirect_to_on_sign_out')
            ?? Config::get( 'usub.redirect_to_on_sign_out' );

        $this->usubService->storeToken( $user1, $user2, $redirectToOnSignOut );

        Auth::loginUsingId( $user2 );

        return redirect( $redirectToOnSignIn );
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function signOut( Request $request )
    {
        $usubToken  = $this->usubService->getUsubTokenInstance();

        if( is_null($usubToken) )
        {
            return $this->flush();
        }

        $adminId    = $this->usubService->getAdminId( $usubToken, Auth::id() );
        $redirectTo = $this->usubService->getRedirectTo( $usubToken );

        if( !is_null( $adminId ) )
        {
            $this->usubService->deleteUsubCookie();

            Auth::loginUsingId( $adminId );

            return redirect( $redirectTo );
        }
        else
        {
            $this->flush();
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    private function flush()
    {
        Auth::logout();
        Session::flush();

        return redirect( Config::get('usub.redirect_to_on_cookie_expiration') );
    }
}
