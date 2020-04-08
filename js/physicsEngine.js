function _PhysicsEngine() {
	this.particles = [];

	this.constants = new function() {
		this.G = 6.674 * Math.pow(10, -11 + 6);
	}

	this.formulas = new function() {
		this.gravitation = function(_massA, _massB, _radius) {
			return PhysicsEngine.constants.G * (_massA * _massB) / (_radius * _radius);
		}
	}
	
	this.world = new function() {
		this.size = new Vector([800, 600]);

	}



	this.update = function() {
		for (let p = 0; p < this.particles.length; p++)
		{
			this.particles[p].applyGravitation();
		}
	}



	

	
	this.getTotalGravVector = function(_particle) {
		let curVector = new Vector([0, 0]);
		for (let i = 0; i < this.particles.length; i++) 
		{
			let curParticle = this.particles[i];
			if (curParticle.id == _particle.id) continue;
			
			curVector.add(
				this.getGravitationVector(_particle, curParticle)
			);
		}
		return curVector;
	}


	this.getGravitationVector = function(_particleA, _particleB) {
		let dVector = _particleA.position.difference(_particleB.position);
		let gravitation = PhysicsEngine.formulas.gravitation(
			_particleA.mass, 
			_particleB.mass,
			dVector.getLength()
		);

		return new Vector([0, 0])
				.setAngle(dVector.getAngle())
				.setLength(gravitation);
	}


}






function Particle({position, mass}) {
	this.id = newId();
	this.mass = mass;
	this.position = new Vector(position);
	this.velocity = new Vector([0, 0]); // x-y-vector

	PhysicsEngine.particles.push(this);
}



function GravParticle({mass, position, radius}) {
	Particle.call(this, {position: position, mass: mass});
	this.radius = radius;

	this.applyGravitation = function() {
		let gravVector = PhysicsEngine.getTotalGravVector(this);
		this.position.add(gravVector);
		RenderEngine.drawVector(this.position.copy(), gravVector.scale(30));
	}

}


