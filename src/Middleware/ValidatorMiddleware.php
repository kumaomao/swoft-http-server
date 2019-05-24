<?php declare(strict_types=1);

namespace Swoft\Http\Server\Middleware;

use function explode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use Swoft\Bean\Annotation\Mapping\Bean;
use Swoft\Bean\BeanFactory;
use Swoft\Bean\Exception\ContainerException;
use Swoft\Http\Message\Request;
use Swoft\Http\Server\Contract\MiddlewareInterface;
use Swoft\Http\Server\Router\Route;
use Swoft\Http\Server\Router\Router;
use Swoft\Validator\Exception\ValidatorException;
use Swoft\Validator\Validator;

/**
 * Class ValidatorMiddleware
 *
 * @Bean()
 *
 * @since 2.0
 */
class ValidatorMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws ContainerException
     * @throws ReflectionException
     * @throws ValidatorException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /* @var Route $route */
        [$status, , $route] = $request->getAttribute(Request::ROUTER_ATTRIBUTE);

        if ($status !== Router::FOUND) {
            return $handler->handle($request);
        }

        // Controller and method
        $handlerId = $route->getHandler();
        [$className, $method] = explode('@', $handlerId);

        //获取路由上定义的参数
        $attributes = $request->getAttributes();
        $attributes = $attributes["swoftRouterHandler"][2]->getParams();
        //这里只获取了POST提交参数的数据
        //原想法是判断提交方式来获取不同的数据，但是提交方式不止POST,GET
        $body = $request->getParsedBody();

        //这里先获取body，body无值再获取QueryParams上的值
        $params = [];
        if(!$body){
            $params = $request->getQueryParams();
        }
        //合并数组
        $data = array_merge($attributes,$body,$params);

        // Fix body is empty string
        $data = empty($data) ? [] : $data;

        /* @var Validator $validator*/
        $validator = BeanFactory::getBean('validator');
        $data = $validator->validate($data, $className, $method);
        $request = $request->withParsedBody($data);

        return $handler->handle($request);
    }
}
