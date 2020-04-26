<!DOCTYPE html>
<html>
	<head>
		<title>Spacegame test</title>
		<meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0' name='viewport'/>


		<script type="text/javascript" src="/JS/jQuery.js"></script>
		<script type="text/javascript" src="js/vector.js"></script>

		<style>
			#gameCanvas {
				position: relative;
				width: 90vw;
				height: auto;
				border: 1px solid red;
			}
		</style>
	</head>	
	<body>
		<canvas id="gameCanvas" width="800" height="600"></canvas>
		

		<script>
			// temporary so things don't get cached
			let antiCache = Math.round(Math.random() * 100000000);
			$.getScript("js/extraFunctions.js?antiCache=" 			+ antiCache, function() {});
			

			const ctx = gameCanvas.getContext("2d");
			ctx.constructor.prototype.circle = function(circle) {
				let position = circle.getPosition();
				ctx.strokeStyle = "#000";
				ctx.beginPath();
				this.ellipse(
					position.value[0],
					position.value[1],
					circle.radius,
					circle.radius,
					0,
					0,
					2 * Math.PI
				);
				ctx.closePath();
				ctx.stroke();
			}

			ctx.constructor.prototype.drawBox = function(box) {
				let points = box.getPoints();

				ctx.strokeStyle = "#000";
				ctx.beginPath();
				ctx.moveTo(points[0].value[0], points[0].value[1]);
				ctx.lineTo(points[1].value[0], points[1].value[1]);
				ctx.lineTo(points[2].value[0], points[2].value[1]);
				ctx.lineTo(points[3].value[0], points[3].value[1]);
				ctx.lineTo(points[0].value[0], points[0].value[1]);
				ctx.closePath();
				ctx.stroke();
			}

			ctx.drawVector = function(_start, _delta, _color = "#f00") {
				let end = _start.copy().add(_delta);
				ctx.strokeStyle = _color;
				ctx.beginPath();
				ctx.moveTo(_start.value[0], _start.value[1]);
				ctx.lineTo(end.value[0], end.value[1]);
				ctx.closePath();
				ctx.stroke();
			}


			const PhysicsEngine = new function() {
				this.world = {
					size: new Vector([800, 600])
				}

				this.bodies = [];
				this.addBody = function(_body) {
					this.bodies.push(_body);
				}

				this.collision = new _PhysicsEngine_collision();




				this.update = function() {
					this.removeBodiesOutsideWorld();

					this.collision.update();

					this.applyCalculations();
				}


				this.applyCalculations = function() {
					for (let s = 0; s < this.bodies.length; s++)
					{
						let cur = this.bodies[s];
						let a = cur.tempValues.force.scale(cur.massData.invMass);
						ctx.drawVector(cur.position.copy(), a.copy().scale(15), "#f00");

						cur.velocity.add(a);
						cur.position.add(cur.velocity);
						cur.position.add(cur.tempValues.positionOffset.scale(-1));

						cur.tempValues.positionOffset = new Vector([0, 0]);
						cur.tempValues.force = new Vector([0, 0]);
					}
				}

				this.removeBodiesOutsideWorld = function() {
					for (let s = this.bodies.length - 1; s >= 0; s--)
					{
						let cur = this.bodies[s];
						if (
							cur.position.value[0] + cur.shape.shapeRange > 0 &&
							cur.position.value[0] - cur.shape.shapeRange < this.world.size.value[0] &&
							cur.position.value[1] + cur.shape.shapeRange > 0 &&
							cur.position.value[1] - cur.shape.shapeRange < this.world.size.value[1]
						) continue;
						let body = this.bodies.splice(s, 1)[0];
					}
				}

			}










			function _PhysicsEngine_collision() {
				this.update = function() {
					for (let s = 0; s < PhysicsEngine.bodies.length; s++)
					{
						let self = PhysicsEngine.bodies[s].shape;
						for (let t = s + 1; t < PhysicsEngine.bodies.length; t++)
						{
							let target = PhysicsEngine.bodies[t].shape;

							let collisions = self.getCollisionData(target);
							if (!collisions.length) continue;
					
							for (let c = 0; c < collisions.length; c++)
							{	
								this.resolveCollision(collisions[c]);
							}	
						}
					}
				}

				this.resolveCollision = function(collider) {
					let self = collider.self.parent.parent;
					let target = collider.target.parent.parent;

					// PositionOffset
					let massPerc = self.massData.mass / (self.massData.mass + target.massData.mass);
					let normal = collider.normal.copy().setLength(collider.depth);

					self.tempValues.positionOffset.add(normal.copy().scale(1 - massPerc));
					target.tempValues.positionOffset.add(normal.copy().scale(-massPerc));




					// Resolve collision
					let relativeVelocity = -self.velocity.difference(target.velocity).dotProduct(collider.normal);
					if (relativeVelocity < 0) return;

					let e = Math.min(self.material.restitution, target.material.restitution);	
					let j = -(1 + e) * relativeVelocity;

					j /= self.massData.invMass + target.massData.invMass;

					let impulse = collider.normal.copy().scale(-j);
					let Fself = impulse.copy().scale(-1 + massPerc);
					let Ftarget = impulse.copy().scale(massPerc);

					self.tempValues.force.add(Fself);
					target.tempValues.force.add(Ftarget);




					// Friction
					let tempSelfVelocity = self.velocity.copy().add(Fself.copy().scale(self.massData.invMass));					
					let tempTargetVelocity = target.velocity.copy().add(Ftarget.copy().scale(target.massData.invMass));

					let newRV = tempSelfVelocity.difference(tempTargetVelocity);

					let perpendicular = collider.normal.getPerpendicular();
					let tangent = perpendicular.scale(perpendicular.dotProduct(newRV));
					tangent.setLength(1);


					let jt = -newRV.dotProduct(tangent);
					jt /= self.massData.invMass + target.massData.invMass;


					let mu = (self.material.staticFriction + target.material.staticFriction) * .5;
					let frictionImpulse;
					if (Math.abs(jt) < -j * mu)
					{
						frictionImpulse = tangent.copy().scale(jt);
					} else {
						let dynamicFriction = (self.material.dynamicFriction + target.material.dynamicFriction) * .5;
						frictionImpulse = tangent.copy().scale(j * dynamicFriction);
					}
					


					let Ffric_self = frictionImpulse.copy().scale(-1 + massPerc);
					let Ffric_target = frictionImpulse.copy().scale(massPerc);

					self.tempValues.force.add(Ffric_self);
					target.tempValues.force.add(Ffric_target);

				}





		
				const jumpTable = {
					Box: {
						Box: boxBox,
						Circle: boxCircle 
					},
					Circle: {
						Box: circleBox,
						Circle: circleCircle
					}
				}

			
				this.collides = function(a, b) {
					let aPosition = a.getPosition();
					let bPosition = b.getPosition();

					let delta = aPosition.difference(bPosition);
					let squareDistance = Math.pow(delta.value[0], 2) + Math.pow(delta.value[1], 2);
					if (squareDistance > Math.pow(a.meshRange + b.meshRange, 2)) return;

					return jumpTable[a.constructor.name][b.constructor.name](a, b);
				}




				function boxBox(box1, box2) {
					let axisA = new Vector([0, 1]).setAngle(box1.getAngle()).setLength(1);
					let axisC = new Vector([0, 1]).setAngle(box2.getAngle()).setLength(1);
					let axis = [
						axisA,
						axisA.getPerpendicular(),
						axisC,
						axisC.getPerpendicular(),
					];

					let distance = box1.getPosition().difference(box2.getPosition()).getLength();

					let minDepth = -Infinity;
					let normalAxis = false;
					let direction = 1;
					for (let a = 0; a < 4; a++) 
					{
						let ownDomain = box1.getProjectedPoints(axis[a]);
						let otherDomain = box2.getProjectedPoints(axis[a]);

						let distanceA = ownDomain[0] - otherDomain[1];
						let distanceB = otherDomain[0] - ownDomain[1]
						let distance = Math.max(distanceA, distanceB);

						if (distance >= 0) return false;
							
						if (distance < minDepth) continue; 
						minDepth = distance;
						normalAxis = axis[a];

						if (distance == distanceA) {
							direction = -1;
						} else direction = 1;
					}


					return {
						normal: normalAxis.scale(direction),
						depth: -minDepth,
						self: box1,
						target: box2
					};
				}

				function circleCircle(circle1, circle2) {
					let delta = circle1.getPosition().difference(circle2.getPosition());
					let distance = delta.getLength();
					if (distance > circle1.radius + circle2.radius) return false;

					return {
						normal: delta.setLength(1),
						depth: circle1.radius + circle2.radius - distance,
						self: circle1,
						target: circle2
					}
				}



				function boxCircle(box, circle) {
					let axisA = new Vector([0, 1]).setAngle(box.getAngle()).setLength(1);
					let points = box.getPoints();

					let axis = [
						axisA,
						axisA.getPerpendicular(),
					];

					// Find closest point on box
					let minDistance = Infinity;
					let minAxis;
					for (let i = 0; i < points.length; i++)
					{
						let delta = points[i].difference(circle.getPosition());
						let squareDistance = Math.pow(delta.value[0], 2) + Math.pow(delta.value[1], 2);

						if (squareDistance > minDistance) continue;
						minDistance = squareDistance;
						minAxis = delta.setLength(1);
					}
					axis.push(minAxis);
		

					let minDepth = -Infinity;
					let normalAxis = false;
					let direction = 1;
					for (let a = 0; a < axis.length; a++) 
					{
						let boxDomain = box.getProjectedPoints(axis[a]);
						let circleDomain = circle.getProjectionDomain(axis[a]);

						let distanceA = boxDomain[0] - circleDomain[1];
						let distanceB = circleDomain[0] - boxDomain[1]
						let distance = Math.max(distanceA, distanceB);

						if (distance >= 0) return false;
							
						if (distance < minDepth) continue; 
						minDepth = distance;
						normalAxis = axis[a];

						if (distance == distanceA) {
							direction = -1;
						} else direction = 1;
					}

					return {
						normal: normalAxis.scale(direction),
						depth: -minDepth,
						self: box,
						target: circle
					};
				}

				function circleBox(circle, box) {
					let result = boxCircle(box, circle);
					if (!result) return false;
					return {
						normal: result.normal.scale(-1),
						depth: result.depth,
						self: circle,
						target: box
					}
				}
			}
























			function Body({position, shapeFactory}) {
				let body = this;

				this.angle 		= 0;
				this.position 	= new Vector(position);
				this.velocity 	= new Vector([0, 0]);


				this.tempValues = {
					force: new Vector([0, 0]),
					positionOffset: new Vector([0, 0])
				}


				this.shape = new Body_Shape(this, shapeFactory);
				this.material = {
					density: .1,
					restitution: .25,//.25
					staticFriction: .5,
					dynamicFriction: .25,
				}

				this.massData = new function() {
					this.mass = 100;
					this.invMass = .01;
					
					this.recalcMass = function() {
						this.mass = body.shape.calcMass();
						this.invMass = 1 / this.mass;
					}
				}


				this.shape.updateCenterOfMass(this.shape.getCenterOfMass());
				this.shape.calcShapeRange();
				this.massData.recalcMass();
			}



			function Body_Shape(_parent, _shapeFactory) {
				this.parent = _parent;
				this.list = _shapeFactory(this);
				this.shapeRange = 0;

				let This = this;



				this.getPosition = function() {
					return this.parent.position.copy();
				}
				this.getAngle = function() {
					return this.parent.angle;
				}

				
				this.getCollisionData = function(_targetShape) {
					let collisions = [];

					let delta = this.getPosition().difference(_targetShape.getPosition());
					let squareDistance = Math.pow(delta.value[0], 2) + Math.pow(delta.value[1], 2) 
					if (squareDistance > Math.pow(this.shapeRange + _targetShape.shapeRange, 2)) return collisions;

					for (let s = 0; s < this.list.length; s++)
					{
						let self = this.list[s];
						for (let t = 0; t < _targetShape.list.length; t++)
						{
							let target = _targetShape.list[t];

							let collider = PhysicsEngine.collision.collides(self, target);
							if (!collider) continue;

							collisions.push(collider);
						}
					}

					return collisions;
				}



				this.draw = function() {
					// let position = this.getPosition();
					// let size = 3;
					// ctx.fillStyle = "#00f";
					// ctx.beginPath();
					// ctx.fillRect(position.value[0] - size, position.value[1] - size, size * 2, size * 2);
					// ctx.closePath();
					// ctx.fill();

					// ctx.circle({
					// 	radius: this.shapeRange,
					// 	getPosition: function () {return This.getPosition()}
					// });

					ctx.drawVector(this.getPosition(), this.parent.velocity.copy().scale(15), "#f00");


					for (let i = 0; i < this.list.length; i++) this.list[i].draw();
				}


				this.calcMass = function() {
					let mass = 0;
					for (let i = 0; i < this.list.length; i++) mass += calcMassPerItem(this.list[i], this.parent.material.density);
					return mass;
				}


				let prevCenterOfMass = new Vector([0, 0]);
				this.updateCenterOfMass = function(_centerOfMass) {
					for (let i = 0; i < this.list.length; i++)
					{
						this.list[i].offset.add(prevCenterOfMass.difference(_centerOfMass).scale(-1));
					}

					prevCenterOfMass = _centerOfMass;
				}

				this.getCenterOfMass = function() {
					let offset = new Vector([0, 0]);
					let massTillNow = 0;

					for (let i = 0; i < this.list.length; i++)
					{
						let cMass = calcMassPerItem(this.list[i], this.parent.material.density);
						massTillNow += cMass;
						let perc = cMass / massTillNow;

						let delta = offset.difference(this.list[i].offset);
						offset.add(delta.scale(perc));
					}

					return offset;
				}

				this.calcShapeRange = function() {
					this.shapeRange = 0;
					for (let i = 0; i < this.list.length; i++)
					{
						let type = this.list[i].constructor.name;
						let range = this.list[i].offset.getLength();
						if (type == "Box") 
						{
							range += this.list[i].shape.getLength();
						} else {
							range += this.list[i].radius;
						}	
						
						if (range < this.shapeRange) continue;
						this.shapeRange = range;
					}
				}



				function calcMassPerItem(_item, _density) {
					return _item.getVolume() * _density;
				}
			}



			function Body_Shape_item({offset}, _parent) {
				this.parent = _parent;
				this.offset = new Vector(offset);
				
				this.getPosition = function() {
					let angle = this.parent.getAngle();
					return this.parent.getPosition().add(this.offset.copy().rotate(angle));
				}
			}

			function Circle({radius, offset}, _parent) {
				Body_Shape_item.call(this, {offset: offset}, _parent);

				this.radius = radius;
				this.meshRange = this.radius;

				this.getProjectionDomain = function(_axis) {
					let projPos = this.getPosition().dotProduct(_axis);

					return [
						projPos - this.radius,
						projPos + this.radius
					];
				}
				this.draw = function() {
					ctx.circle(this);
				}
				
				this.getVolume = function() {
					return 4 / 3 * Math.PI * Math.pow(this.radius, 3);
				}
			}





			function Box({offset, shape, angle = 0}, _parent) {
				Body_Shape_item.call(this, {offset: offset}, _parent);

				this.shape = new Vector(shape);
				this.meshRange = this.shape.getLength();
				this.angle = angle;

				this.getAngle = function() {
					return this.parent.getAngle() + this.angle;
				}


				this.getPoints = function() {
					let position 	= this.getPosition();
					let angle 		= this.getAngle();

					let topLeft 	= position.copy().add(this.shape.copy().scale(-1).rotate(angle));
					let bottomRight = position.copy().add(this.shape.copy().rotate(angle));

					let dTR = new Vector([this.shape.value[0], 0]).scale(2).rotate(angle);
					let topRight = topLeft.copy().add(dTR);
					let bottomLeft = bottomRight.copy().add(dTR.scale(-1));
					return [topLeft, topRight, bottomRight, bottomLeft];
				}

				this.getProjectedPoints = function(_projector) {
					let ownPoints = this.getPoints();
					let min = Infinity;
					let max = -Infinity;

					for (let i = 0; i < ownPoints.length; i++)
					{
						let value = _projector.dotProduct(ownPoints[i]);
						if (value > max) max = value;
						if (value < min) min = value;
					}
					return [min, max];
				}

				this.draw = function() {
					ctx.drawBox(this);
				}

				this.getVolume = function() {
					let height = this.shape.value[0] + this.shape.value[1];
					return this.shape.value[0] * this.shape.value[1] * 4 * height;
				}
			}







			gameCanvas.onmousemove = function(_e) {
				let mousePos = new Vector([_e.layerX, _e.layerY]);
				// body1.position = mousePos;
			}

			
			// let body1 = new Body({
			// 	position: [500, 300], 
			// 	shapeFactory: function(_this) {
			// 		return [
			// 			new Box({offset: [0, 0], shape: [2, 200]}, _this),
			// 			new Circle({offset: [0, 0], radius: 40}, _this),
			// 			new Box({offset: [40 + 50, 0], shape: [30, 20]}, _this),
			// 			new Box({offset: [35 + 100, 45], shape: [5, 40]}, _this),
			// 			new Box({offset: [40 + 50, 90], shape: [50, 5]}, _this),
			// 		];
			// 	}
			// });
			
			let body2 = new Body({
				position: [150, 400], 
				shapeFactory: function(_this) {
					return [
						// new Box({offset: [0, 0], shape: [10, 20]}, _this),
						new Circle({offset: [35, 0], radius: 30}, _this),
						// new Box({offset: [35 + 100, 45], shape: [5, 40]}, _this),
						// new Box({offset: [40 + 50, 90], shape: [50, 5]}, _this),
					];
				}
			});
			let body3 = new Body({
				position: [100, 300], 
				shapeFactory: function(_this) {
					return [
						new Box({offset: [0, 0], shape: [10, 100]}, _this),
						// new Circle({offset: [10, 0], radius: 30}, _this),
						// new Box({offset: [35 + 100, 45], shape: [5, 40]}, _this),
						// new Box({offset: [40 + 50, 90], shape: [50, 5]}, _this),
					];
				}
			});

			// PhysicsEngine.addBody(body2);
			// PhysicsEngine.addBody(body1);
			// PhysicsEngine.addBody(body3);
			// body1.velocity = new Vector([-.3, 0]);
			// body2.velocity = new Vector([-.1, -1]);

		

			for (let i = 0; i < 500; i++) {
				let position = [Math.random() * gameCanvas.width, Math.random() * gameCanvas.height];

				let body = new Body({
					position: position,
					shapeFactory: function(_this) {
						return [
							new Box({offset: [0, 0], shape: [40 * Math.random() + 5, 40 * Math.random() + 5], angle: Math.random() * 2 * Math.PI}, _this)
						];
					}
				});
				if (Math.random() > .5) body = new Body({
					position: position,
					shapeFactory: function(_this) {
						return [
							new Circle({offset: [0, 0], radius: 30 * Math.random() + 5}, _this),
						];
					}
				});

				PhysicsEngine.addBody(body);
			}


			let running = true;
			let lastRun = new Date();
			function loop() {
				if (!running) return;
				ctx.clearRect(0, 0, gameCanvas.width, gameCanvas.height);

				ctx.strokeStyle = "#f00";
				// body2.angle += .05;
				// body1.angle += -.01;

				PhysicsEngine.update();

				for (let s = 0; s < PhysicsEngine.bodies.length; s++)
				{
					PhysicsEngine.bodies[s].shape.draw();
				}
				
				ctx.stroke();

				requestAnimationFrame(loop);
				
				let dt = (new Date() - lastRun) / 1000;
				ctx.fillStyle = "#f00";
				ctx.beginPath();
				ctx.fillText("Fps: " + Math.round(1 / dt), 10, 20);
				ctx.closePath();
				ctx.fill();
				
				lastRun = new Date();
			}

			loop();

			
		</script>
<!-- 
		<script type="text/javascript" src="js/extraFunctions.js"></script>
		<script type="text/javascript" src="js/vector.js"></script>
		<script type="text/javascript" src="js/animator.js"></script>

		<script type="text/javascript" src="js/physicsEngine/gravity.js"></script>
		<script type="text/javascript" src="js/physicsEngine/collision.js"></script>
		<script type="text/javascript" src="js/physicsEngine/engine.js"></script>

		<script type="text/javascript" src="js/renderEngine.js"></script>
		<script type="text/javascript" src="js/inputHandler.js"></script>
		<script type="text/javascript" src="js/game.js"></script>
		<script type="text/javascript" src="js/app.js"></script>
 -->
	</body>
</html>	